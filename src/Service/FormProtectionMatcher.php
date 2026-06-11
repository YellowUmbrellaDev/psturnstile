<?php

namespace Sigterm\PsTurnstile\Service;

use Tools;

/**
 * Decides which front-office requests must carry a valid Turnstile token and
 * on which pages the Cloudflare api.js script should be loaded.
 */
class FormProtectionMatcher
{
    public function __construct(private readonly ConfigurationProvider $configurationProvider)
    {
    }

    /**
     * Returns the protected forms that were actually submitted in this request.
     *
     * @return array<int, array{name: string, submit_parameter: string}>
     */
    public function getSubmittedMatches(object $controller): array
    {
        if (!$this->configurationProvider->isConfigured()) {
            return [];
        }

        $matches = [];
        foreach ($this->getAllRules() as $rule) {
            if ($this->matchesRule($controller, $rule, true)) {
                $matches[] = [
                    'name' => $rule['name'],
                    'submit_parameter' => $rule['submit_parameter'],
                ];
            }
        }

        return $matches;
    }

    /**
     * Whether the Cloudflare api.js script should be registered on this page.
     * Only rules with at least one page context constraint (controller,
     * php_self or uri_contains) can trigger asset loading.
     */
    public function shouldLoadAssets(?object $controller): bool
    {
        if (!$this->configurationProvider->isConfigured() || $controller === null) {
            return false;
        }

        foreach ($this->getAllRules() as $rule) {
            if ($this->matchesRule($controller, $rule, false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{name: string, enabled: bool, controller: string, php_self: string, uri_contains: string, submit_parameter: string}>
     */
    private function getAllRules(): array
    {
        return array_merge($this->getDefaultRules(), $this->configurationProvider->getCustomRules());
    }

    /**
     * Built-in rules. php_self is used for matching because it is a stable,
     * documented public property of core front controllers, unlike the
     * controller class short name.
     *
     * The customer (registration) form always posts a hidden submitCreate
     * field; it is rendered on the registration page, the authentication page
     * and during checkout (guest / account creation step). The identity page
     * (profile edit) is intentionally NOT covered: logged-in customers editing
     * their profile are not challenged.
     *
     * @return array<int, array{name: string, enabled: bool, controller: string, php_self: string, uri_contains: string, submit_parameter: string}>
     */
    private function getDefaultRules(): array
    {
        $rules = [];

        if ($this->configurationProvider->isRegistrationEnabled()) {
            foreach (['registration', 'authentication', 'order'] as $phpSelf) {
                $rules[] = [
                    'name' => 'Registration',
                    'enabled' => true,
                    'controller' => '',
                    'php_self' => $phpSelf,
                    'uri_contains' => '',
                    'submit_parameter' => 'submitCreate',
                ];
            }
        }

        if ($this->configurationProvider->isLoginEnabled()) {
            $rules[] = [
                'name' => 'Login',
                'enabled' => true,
                'controller' => '',
                'php_self' => 'authentication',
                'uri_contains' => '',
                'submit_parameter' => 'submitLogin',
            ];
        }

        if ($this->configurationProvider->isContactEnabled()) {
            $rules[] = [
                'name' => 'Contact',
                'enabled' => true,
                'controller' => '',
                'php_self' => 'contact',
                'uri_contains' => '',
                'submit_parameter' => 'submitMessage',
            ];
        }

        return $rules;
    }

    /**
     * @param array{name: string, enabled: bool, controller: string, php_self: string, uri_contains: string, submit_parameter: string} $rule
     */
    private function matchesRule(object $controller, array $rule, bool $requireSubmit): bool
    {
        if (empty($rule['enabled'])) {
            return false;
        }

        if ($requireSubmit && !Tools::isSubmit($rule['submit_parameter'])) {
            return false;
        }

        $controllerName = strtolower((string) ($controller->controller_name ?? ''));
        $phpSelf = strtolower((string) ($controller->php_self ?? ''));
        $requestUri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));

        $hasContextConstraint = false;

        if ($rule['controller'] !== '') {
            $hasContextConstraint = true;
            if ($controllerName !== strtolower($rule['controller'])) {
                return false;
            }
        }

        if ($rule['php_self'] !== '') {
            $hasContextConstraint = true;
            if ($phpSelf !== strtolower($rule['php_self'])) {
                return false;
            }
        }

        if ($rule['uri_contains'] !== '') {
            $hasContextConstraint = true;
            if (!str_contains($requestUri, strtolower($rule['uri_contains']))) {
                return false;
            }
        }

        // Submit-only rules (no page context) match any submitted request but
        // never trigger asset preloading, since the page cannot be predicted.
        return $hasContextConstraint || $requireSubmit;
    }
}
