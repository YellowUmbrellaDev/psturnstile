<?php

declare(strict_types=1);

namespace Sigterm\PsTurnstile\Form;

use Sigterm\PsTurnstile\Service\ConfigurationProvider;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Builds the configuration form and persists submitted data through
 * the form data provider (which validates and writes the settings).
 */
class ConfigurationFormHandler
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly ConfigurationFormDataProvider $formDataProvider,
        private readonly ConfigurationProvider $configurationProvider
    ) {
    }

    public function getForm(): FormInterface
    {
        $data = $this->formDataProvider->getData();

        // Never echo the stored secret back to the browser. An empty
        // submitted value means "keep the current secret" (see save()).
        $data['secret_key'] = '';

        return $this->formFactory->create(ConfigurationFormType::class, $data);
    }

    /**
     * Persists the submitted configuration.
     *
     * @param array<string, mixed> $data
     *
     * @return array<int, array{key: string, domain: string, parameters: array}> errors, empty on success
     */
    public function save(array $data): array
    {
        if (trim((string) ($data['secret_key'] ?? '')) === '') {
            $data['secret_key'] = $this->configurationProvider->getSecretKey();
        }

        return $this->formDataProvider->setData($data);
    }
}
