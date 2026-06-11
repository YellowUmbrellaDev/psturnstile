<?php

declare(strict_types=1);

namespace Sigterm\PsTurnstile\Controller;

use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use Sigterm\PsTurnstile\Form\ConfigurationFormHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back office configuration page for the psturnstile module.
 *
 * Registered as a service in config/services.yml (required by PrestaShop 9 /
 * Symfony 6.4).  The form handler is injected via the action method using
 * the #[Autowire] attribute so the container does not need to expose it by
 * its FQCN.
 */
class ConfigurationController extends PrestaShopAdminController
{
    public function index(
        Request $request,
        #[Autowire(service: 'sigterm.psturnstile.configuration_form_handler')]
        ConfigurationFormHandler $formHandler,
    ): Response {
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
}
