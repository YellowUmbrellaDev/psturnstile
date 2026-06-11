<?php
/**
 * Cloudflare Turnstile protection for PrestaShop front-office forms.
 *
 * @author    Sigterm
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
use Sigterm\PsTurnstile\Service\ConfigurationProvider;
use Sigterm\PsTurnstile\Service\FormProtectionMatcher;
use Sigterm\PsTurnstile\Service\TurnstileVerifier;

class Psturnstile extends Module implements WidgetInterface
{
    private ?ConfigurationProvider $psturnstileConfiguration = null;
    private ?TurnstileVerifier $psturnstileVerifier = null;
    private ?FormProtectionMatcher $psturnstileMatcher = null;

    public function __construct()
    {
        $this->name = 'psturnstile';
        $this->tab = 'front_office_features';
        $this->version = '0.1.0';
        $this->author = 'Sigterm';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => '9.99.99',
        ];
        $this->bootstrap = true;

        // Hidden tab so the Symfony route resolves permissions correctly.
        // route_name links the tab directly to the Symfony route; the
        // _legacy_controller in routes.yml provides the fallback mapping.
        $this->tabs = [
            [
                'route_name' => 'admin_psturnstile_configuration',
                'class_name' => 'AdminPsturnstileConfiguration',
                'visible' => false,
                'name' => 'Cloudflare Turnstile Configuration',
                'parent_class_name' => 'AdminParentModulesSf',
            ],
        ];

        parent::__construct();

        $this->displayName = $this->trans('Cloudflare Turnstile', [], 'Modules.Psturnstile.Admin');
        $this->description = $this->trans('Protects PrestaShop front-office forms with Cloudflare Turnstile.', [], 'Modules.Psturnstile.Admin');
        $this->confirmUninstall = $this->trans('This will remove all Turnstile configuration stored by the module.', [], 'Modules.Psturnstile.Admin');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installConfiguration()
            && $this->registerHook('displayCustomerAccountForm')
            && $this->registerHook('actionFrontControllerInitAfter')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayPsTurnstileWidget');
    }

    public function uninstall(): bool
    {
        return $this->uninstallConfiguration()
            && parent::uninstall();
    }

    /**
     * Redirect to the modern Symfony configuration page.
     *
     * This method must never throw — PrestaShop renders the return value
     * inside the Back Office module list, and an uncaught exception causes
     * an HTTP 500 for the entire page.
     *
     * @return string Empty on successful redirect; error HTML when routing fails
     */
    public function getContent(): string
    {
        // Try the Symfony router — the only valid path for this module.
        try {
            $router = $this->get('router');
            if ($router !== null) {
                Tools::redirectAdmin($router->generate('admin_psturnstile_configuration'));

                return ''; // unreachable after redirect, satisfies return type
            }
        } catch (\Throwable $e) {
            // Router unavailable or route not registered — fall through to error.
        }

        // Return a visible error so the admin can act.
        return '<div class="alert alert-danger">'
            . $this->trans(
                'Unable to open the Cloudflare Turnstile configuration page. Try clearing the Symfony cache (Administration → Performance) and verify the module is installed correctly.',
                [],
                'Modules.Psturnstile.Admin'
            )
            . '</div>';
    }

    /**
     * Renders the Turnstile widget inside the customer (registration) form.
     *
     * This hook is rendered by both official themes inside the <form> element of
     * customer-form.tpl, which makes it theme-agnostic. The customer form is also
     * used on the identity page (profile edit) where we deliberately do not render
     * nor validate the challenge for logged-in customers.
     */
    public function hookDisplayCustomerAccountForm(array $params): string
    {
        $configuration = $this->getPsturnstileConfiguration();
        if (!$configuration->isConfigured() || !$configuration->isRegistrationEnabled()) {
            return '';
        }

        $phpSelf = (string) ($this->context->controller?->php_self ?? '');
        if ($phpSelf === 'identity') {
            return '';
        }

        return $this->renderWidget('displayCustomerAccountForm', $params);
    }

    /**
     * Server-side enforcement: blocks protected form submissions when the
     * Turnstile token is missing or invalid, before controllers postProcess().
     */
    public function hookActionFrontControllerInitAfter(array $params): void
    {
        if (!isset($params['controller']) || !is_object($params['controller'])) {
            return;
        }

        $matches = $this->getPsturnstileMatcher()->getSubmittedMatches($params['controller']);
        if ($matches === []) {
            return;
        }

        if ($this->validateCurrentTurnstileToken()) {
            return;
        }

        $params['controller']->errors[] = $this->trans('Please complete the security check.', [], 'Modules.Psturnstile.Shop');

        // Neutralize the submit parameter so the controller's postProcess()
        // skips the protected action (login, message sending, account creation...).
        foreach ($matches as $match) {
            if (!empty($match['submit_parameter'])) {
                unset($_POST[$match['submit_parameter']], $_GET[$match['submit_parameter']], $_REQUEST[$match['submit_parameter']]);
            }
        }
    }

    public function hookActionFrontControllerSetMedia(array $params): void
    {
        $configuration = $this->getPsturnstileConfiguration();
        if (!$configuration->shouldLoadApiScript() || !$this->getPsturnstileMatcher()->shouldLoadAssets($this->context->controller)) {
            return;
        }

        $this->context->controller->registerJavascript(
            'psturnstile-cloudflare-api',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            [
                'server' => 'remote',
                'position' => 'bottom',
                'priority' => 100,
                'attributes' => 'async defer',
            ]
        );
    }

    public function hookDisplayPsTurnstileWidget(array $params): string
    {
        return $this->renderWidget('displayPsTurnstileWidget', $params);
    }

    public function renderWidget($hookName, array $configuration): string
    {
        if (!$this->getPsturnstileConfiguration()->isConfigured()) {
            return '';
        }

        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch('module:psturnstile/views/templates/hook/turnstile.tpl');
    }

    public function getWidgetVariables($hookName, array $configuration): array
    {
        $configurationProvider = $this->getPsturnstileConfiguration();

        return [
            'psturnstile_site_key' => $configurationProvider->getSiteKey(),
            'psturnstile_theme' => $configurationProvider->getTheme(),
            'psturnstile_size' => $configurationProvider->getSize(),
        ];
    }

    private function installConfiguration(): bool
    {
        return $this->getPsturnstileConfiguration()->installDefaults();
    }

    private function uninstallConfiguration(): bool
    {
        return $this->getPsturnstileConfiguration()->deleteAll();
    }

    private function validateCurrentTurnstileToken(): bool
    {
        $configuration = $this->getPsturnstileConfiguration();
        $token = (string) Tools::getValue('cf-turnstile-response');
        $remoteIp = Tools::getRemoteAddr();

        return $this->getPsturnstileVerifier()->verify(
            $token,
            $configuration->getSecretKey(),
            $remoteIp,
            $configuration->isFailOpenEnabled()
        );
    }

    private function getPsturnstileConfiguration(): ConfigurationProvider
    {
        if ($this->psturnstileConfiguration instanceof ConfigurationProvider) {
            return $this->psturnstileConfiguration;
        }

        try {
            $service = $this->get('sigterm.psturnstile.configuration_provider');
            if ($service instanceof ConfigurationProvider) {
                return $this->psturnstileConfiguration = $service;
            }
        } catch (Exception $exception) {
            // Container unavailable (e.g. early front office bootstrap); fall back below.
        }

        return $this->psturnstileConfiguration = new ConfigurationProvider();
    }

    private function getPsturnstileVerifier(): TurnstileVerifier
    {
        if ($this->psturnstileVerifier instanceof TurnstileVerifier) {
            return $this->psturnstileVerifier;
        }

        try {
            $service = $this->get('sigterm.psturnstile.turnstile_verifier');
            if ($service instanceof TurnstileVerifier) {
                return $this->psturnstileVerifier = $service;
            }
        } catch (Exception $exception) {
            // Fall back below.
        }

        return $this->psturnstileVerifier = new TurnstileVerifier();
    }

    private function getPsturnstileMatcher(): FormProtectionMatcher
    {
        if ($this->psturnstileMatcher instanceof FormProtectionMatcher) {
            return $this->psturnstileMatcher;
        }

        try {
            $service = $this->get('sigterm.psturnstile.form_protection_matcher');
            if ($service instanceof FormProtectionMatcher) {
                return $this->psturnstileMatcher = $service;
            }
        } catch (Exception $exception) {
            // Fall back below.
        }

        return $this->psturnstileMatcher = new FormProtectionMatcher($this->getPsturnstileConfiguration());
    }
}
