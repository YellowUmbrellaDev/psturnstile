<?php

declare(strict_types=1);

namespace Sigterm\PsTurnstile\Form;

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ConfigurationFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('site_key', TextType::class, [
                'label' => $this->trans('Site key', 'Modules.Psturnstile.Admin'),
                'help' => $this->trans('Public site key of your Cloudflare Turnstile widget.', 'Modules.Psturnstile.Admin'),
                'required' => false,
            ])
            ->add('secret_key', PasswordType::class, [
                'label' => $this->trans('Secret key', 'Modules.Psturnstile.Admin'),
                'help' => $this->trans('Secret key from the Cloudflare Turnstile dashboard. Leave empty to keep the currently stored secret.', 'Modules.Psturnstile.Admin'),
                'required' => false,
            ])
            ->add('enable_registration', SwitchType::class, [
                'label' => $this->trans('Protect customer registration form', 'Modules.Psturnstile.Admin'),
                'required' => false,
            ])
            ->add('enable_login', SwitchType::class, [
                'label' => $this->trans('Protect customer login form', 'Modules.Psturnstile.Admin'),
                'required' => false,
            ])
            ->add('enable_contact', SwitchType::class, [
                'label' => $this->trans('Protect contact form', 'Modules.Psturnstile.Admin'),
                'required' => false,
            ])
            ->add('fail_open', SwitchType::class, [
                'label' => $this->trans('Fail open', 'Modules.Psturnstile.Admin'),
                'help' => $this->trans('Accept submissions when the Cloudflare verification service cannot be reached. Safer to keep disabled.', 'Modules.Psturnstile.Admin'),
                'required' => false,
            ])
            ->add('load_api_script', SwitchType::class, [
                'label' => $this->trans('Load Turnstile API script', 'Modules.Psturnstile.Admin'),
                'help' => $this->trans('Disable only if your theme already loads the Cloudflare Turnstile script.', 'Modules.Psturnstile.Admin'),
                'required' => false,
            ])
            ->add('theme', ChoiceType::class, [
                'label' => $this->trans('Widget theme', 'Modules.Psturnstile.Admin'),
                'choices' => [
                    $this->trans('Auto', 'Modules.Psturnstile.Admin') => 'auto',
                    $this->trans('Light', 'Modules.Psturnstile.Admin') => 'light',
                    $this->trans('Dark', 'Modules.Psturnstile.Admin') => 'dark',
                ],
            ])
            ->add('size', ChoiceType::class, [
                'label' => $this->trans('Widget size', 'Modules.Psturnstile.Admin'),
                'choices' => [
                    $this->trans('Normal', 'Modules.Psturnstile.Admin') => 'normal',
                    $this->trans('Compact', 'Modules.Psturnstile.Admin') => 'compact',
                    $this->trans('Flexible', 'Modules.Psturnstile.Admin') => 'flexible',
                ],
            ])
            ->add('custom_rules', TextareaType::class, [
                'label' => $this->trans('Custom form rules (JSON)', 'Modules.Psturnstile.Admin'),
                'help' => $this->trans('JSON array of rules. Each rule is an object with "name", "enabled", "controller", "php_self", "uri_contains" and a mandatory "submit_parameter".', 'Modules.Psturnstile.Admin'),
                'required' => false,
                'attr' => [
                    'rows' => 8,
                ],
                'constraints' => [
                    new Callback(static function (?string $value, ExecutionContextInterface $context): void {
                        $value = trim((string) $value);
                        if ($value === '') {
                            return;
                        }
                        json_decode($value);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $context->addViolation('Invalid JSON syntax: ' . json_last_error_msg());
                        }
                    }),
                ],
            ]);
    }
}
