<?php

declare(strict_types=1);

namespace Sigterm\PsTurnstile\Form;

use Sigterm\PsTurnstile\Service\ConfigurationProvider;

/**
 * Reads and persists the module configuration as a plain array, validating
 * the custom rules JSON before anything is written.
 */
class ConfigurationFormDataProvider
{
    public function __construct(private readonly ConfigurationProvider $configurationProvider)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->configurationProvider->getData();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, array{key: string, domain: string, parameters: array}> validation errors, empty on success
     */
    public function setData(array $data): array
    {
        $errors = $this->validateRequiredCredentials($data);
        $errors = array_merge($errors, $this->validateCustomRules((string) ($data['custom_rules'] ?? '')));
        if ($errors !== []) {
            return $errors;
        }

        if (!$this->configurationProvider->saveData($data)) {
            return [$this->error('Could not save the Turnstile configuration.')];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, array{key: string, domain: string, parameters: array}>
     */
    private function validateRequiredCredentials(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['site_key'] ?? '')) === '') {
            $errors[] = $this->error('Site key is required.');
        }

        if ($this->configurationProvider->getSecretKey() === '' && trim((string) ($data['secret_key'] ?? '')) === '') {
            $errors[] = $this->error('Secret key is required.');
        }

        return $errors;
    }

    /**
     * @return array<int, array{key: string, domain: string, parameters: array}>
     */
    private function validateCustomRules(string $customRulesJson): array
    {
        $customRulesJson = trim($customRulesJson);
        if ($customRulesJson === '') {
            return [];
        }

        $decoded = json_decode($customRulesJson, true);
        if (!is_array($decoded) || ($decoded !== [] && array_keys($decoded) !== range(0, count($decoded) - 1))) {
            return [$this->error('Custom rules must be a valid JSON array of rule objects.')];
        }

        $errors = [];
        foreach ($decoded as $index => $rule) {
            if (!is_array($rule) || trim((string) ($rule['submit_parameter'] ?? '')) === '') {
                $errors[] = $this->error(
                    'Custom rule #%rule% must be an object with a non-empty "submit_parameter".',
                    ['%rule%' => (int) $index + 1]
                );
            }
        }

        return $errors;
    }

    /**
     * @param array<string, int|string> $parameters
     *
     * @return array{key: string, domain: string, parameters: array}
     */
    private function error(string $key, array $parameters = []): array
    {
        return [
            'key' => $key,
            'domain' => 'Modules.Psturnstile.Admin',
            'parameters' => $parameters,
        ];
    }
}
