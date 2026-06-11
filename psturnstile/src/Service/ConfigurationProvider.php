<?php

namespace Sigterm\PsTurnstile\Service;

use Configuration;

class ConfigurationProvider
{
    public const SITE_KEY = 'PSTURNSTILE_SITE_KEY';
    public const SECRET_KEY = 'PSTURNSTILE_SECRET_KEY';
    public const ENABLE_REGISTRATION = 'PSTURNSTILE_ENABLE_REGISTRATION';
    public const ENABLE_LOGIN = 'PSTURNSTILE_ENABLE_LOGIN';
    public const ENABLE_CONTACT = 'PSTURNSTILE_ENABLE_CONTACT';
    public const FAIL_OPEN = 'PSTURNSTILE_FAIL_OPEN';
    public const LOAD_API_SCRIPT = 'PSTURNSTILE_LOAD_API_SCRIPT';
    public const THEME = 'PSTURNSTILE_THEME';
    public const SIZE = 'PSTURNSTILE_SIZE';
    public const CUSTOM_RULES = 'PSTURNSTILE_CUSTOM_RULES';

    private const ALL_KEYS = [
        self::SITE_KEY,
        self::SECRET_KEY,
        self::ENABLE_REGISTRATION,
        self::ENABLE_LOGIN,
        self::ENABLE_CONTACT,
        self::FAIL_OPEN,
        self::LOAD_API_SCRIPT,
        self::THEME,
        self::SIZE,
        self::CUSTOM_RULES,
    ];

    public function installDefaults(): bool
    {
        return Configuration::updateValue(self::SITE_KEY, '')
            && Configuration::updateValue(self::SECRET_KEY, '')
            && Configuration::updateValue(self::ENABLE_REGISTRATION, '1')
            && Configuration::updateValue(self::ENABLE_LOGIN, '1')
            && Configuration::updateValue(self::ENABLE_CONTACT, '1')
            && Configuration::updateValue(self::FAIL_OPEN, '0')
            && Configuration::updateValue(self::LOAD_API_SCRIPT, '1')
            && Configuration::updateValue(self::THEME, 'auto')
            && Configuration::updateValue(self::SIZE, 'normal')
            && Configuration::updateValue(self::CUSTOM_RULES, "[]");
    }

    public function deleteAll(): bool
    {
        $success = true;
        foreach (self::ALL_KEYS as $key) {
            $success = Configuration::deleteByName($key) && $success;
        }

        return $success;
    }

    public function getData(): array
    {
        return [
            'site_key' => $this->getSiteKey(),
            'secret_key' => $this->getSecretKey(),
            'enable_registration' => $this->isRegistrationEnabled(),
            'enable_login' => $this->isLoginEnabled(),
            'enable_contact' => $this->isContactEnabled(),
            'fail_open' => $this->isFailOpenEnabled(),
            'load_api_script' => $this->shouldLoadApiScript(),
            'theme' => $this->getTheme(),
            'size' => $this->getSize(),
            'custom_rules' => $this->getCustomRulesJson(),
        ];
    }

    public function saveData(array $data): bool
    {
        $customRules = $this->normalizeCustomRulesJson((string) ($data['custom_rules'] ?? '[]'));

        return Configuration::updateValue(self::SITE_KEY, trim((string) ($data['site_key'] ?? '')))
            && Configuration::updateValue(self::SECRET_KEY, trim((string) ($data['secret_key'] ?? '')))
            && Configuration::updateValue(self::ENABLE_REGISTRATION, !empty($data['enable_registration']) ? '1' : '0')
            && Configuration::updateValue(self::ENABLE_LOGIN, !empty($data['enable_login']) ? '1' : '0')
            && Configuration::updateValue(self::ENABLE_CONTACT, !empty($data['enable_contact']) ? '1' : '0')
            && Configuration::updateValue(self::FAIL_OPEN, !empty($data['fail_open']) ? '1' : '0')
            && Configuration::updateValue(self::LOAD_API_SCRIPT, !empty($data['load_api_script']) ? '1' : '0')
            && Configuration::updateValue(self::THEME, $this->normalizeChoice((string) ($data['theme'] ?? 'auto'), ['auto', 'light', 'dark'], 'auto'))
            && Configuration::updateValue(self::SIZE, $this->normalizeChoice((string) ($data['size'] ?? 'normal'), ['normal', 'compact', 'flexible'], 'normal'))
            && Configuration::updateValue(self::CUSTOM_RULES, $customRules);
    }

    public function getSiteKey(): string
    {
        return trim((string) Configuration::get(self::SITE_KEY));
    }

    public function getSecretKey(): string
    {
        return trim((string) Configuration::get(self::SECRET_KEY));
    }

    public function isConfigured(): bool
    {
        return $this->getSiteKey() !== '' && $this->getSecretKey() !== '';
    }

    public function isRegistrationEnabled(): bool
    {
        return (bool) Configuration::get(self::ENABLE_REGISTRATION);
    }

    public function isLoginEnabled(): bool
    {
        return (bool) Configuration::get(self::ENABLE_LOGIN);
    }

    public function isContactEnabled(): bool
    {
        return (bool) Configuration::get(self::ENABLE_CONTACT);
    }

    public function isFailOpenEnabled(): bool
    {
        return (bool) Configuration::get(self::FAIL_OPEN);
    }

    public function shouldLoadApiScript(): bool
    {
        return (bool) Configuration::get(self::LOAD_API_SCRIPT);
    }

    public function getTheme(): string
    {
        return $this->normalizeChoice((string) Configuration::get(self::THEME), ['auto', 'light', 'dark'], 'auto');
    }

    public function getSize(): string
    {
        return $this->normalizeChoice((string) Configuration::get(self::SIZE), ['normal', 'compact', 'flexible'], 'normal');
    }

    public function getCustomRulesJson(): string
    {
        return $this->normalizeCustomRulesJson((string) Configuration::get(self::CUSTOM_RULES));
    }

    /**
     * @return array<int, array{name: string, enabled: bool, controller: string, php_self: string, uri_contains: string, submit_parameter: string}>
     */
    public function getCustomRules(): array
    {
        $decoded = json_decode($this->getCustomRulesJson(), true);
        if (!is_array($decoded)) {
            return [];
        }

        $rules = [];
        foreach ($decoded as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $submitParameter = trim((string) ($rule['submit_parameter'] ?? ''));
            if ($submitParameter === '') {
                continue;
            }

            $rules[] = [
                'name' => trim((string) ($rule['name'] ?? 'Custom form')),
                'enabled' => array_key_exists('enabled', $rule) ? (bool) $rule['enabled'] : true,
                'controller' => trim((string) ($rule['controller'] ?? '')),
                'php_self' => trim((string) ($rule['php_self'] ?? '')),
                'uri_contains' => trim((string) ($rule['uri_contains'] ?? '')),
                'submit_parameter' => $submitParameter,
            ];
        }

        return $rules;
    }

    public function normalizeCustomRulesJson(string $customRulesJson): string
    {
        $customRulesJson = trim($customRulesJson);
        if ($customRulesJson === '') {
            return '[]';
        }

        $decoded = json_decode($customRulesJson, true);
        if (!is_array($decoded)) {
            return '[]';
        }

        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function normalizeChoice(string $value, array $allowedValues, string $default): string
    {
        return in_array($value, $allowedValues, true) ? $value : $default;
    }
}
