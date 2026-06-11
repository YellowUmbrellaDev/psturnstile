<?php

declare(strict_types=1);

namespace Sigterm\PsTurnstile\Controller;

use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use RuntimeException;
use Sigterm\PsTurnstile\Form\ConfigurationFormHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back office configuration page for the psturnstile module.
 *
 * This controller is intentionally NOT registered in config/services.yml:
 * Symfony's controller resolver instantiates it and injects the container
 * (the pattern documented for PrestaShop 9 module configuration pages).
 */
class ConfigurationController extends PrestaShopAdminController
{
    public function index(Request $request): Response
    {
        $formHandler = $this->getConfigurationFormHandler();

        $form = $formHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $formHandler->save((array) $form->getData());

            if ($errors === []) {
                $this->addFlash('success', $this->trans('Successful update.', [], 'Admin.Notifications.Success'));

                return $this->redirectToRoute('admin_psturnstile_configuration');
            }

            $this->addFlashErrors($errors);
        }

        return $this->render('@Modules/psturnstile/views/templates/admin/configuration.html.twig', [
            'layoutTitle' => $this->trans('Cloudflare Turnstile', [], 'Modules.Psturnstile.Admin'),
            'psturnstileConfigurationForm' => $form->createView(),
        ]);
    }

    private function getConfigurationFormHandler(): ConfigurationFormHandler
    {
        $handler = $this->container->get('sigterm.psturnstile.configuration_form_handler');

        if (!$handler instanceof ConfigurationFormHandler) {
            throw new RuntimeException('psturnstile configuration form handler service is misconfigured.');
        }

        return $handler;
    }
}
