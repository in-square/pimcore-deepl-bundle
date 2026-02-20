<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\Controller\Admin;

use InSquare\PimcoreDeeplBundle\Service\DeeplSettings;
use Pimcore\Bundle\AdminBundle\Controller\AdminAbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class SettingsController extends AdminAbstractController
{
    #[Route('/settings', name: 'insquare_pimcore_deepl_settings', methods: ['GET'])]
    public function settingsAction(DeeplSettings $settings): JsonResponse
    {
        $this->checkPermission('settings');

        return $this->adminJson([
            'configured' => $settings->isConfigured(),
            'accountType' => $settings->getAccountType(),
        ]);
    }
}
