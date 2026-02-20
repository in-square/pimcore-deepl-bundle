<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\Service;

use Pimcore\Model\WebsiteSetting;

final class DeeplSettings
{
    public const SETTING_API_KEY = 'deepl_api_key';
    public const SETTING_ACCOUNT_TYPE = 'deepl_account_type';

    public function getApiKey(): ?string
    {
        $setting = WebsiteSetting::getByName(self::SETTING_API_KEY);
        $value = $setting?->getData();

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    public function getAccountType(): string
    {
        $setting = WebsiteSetting::getByName(self::SETTING_ACCOUNT_TYPE);
        $value = $setting?->getData();

        if (!is_string($value)) {
            return 'FREE';
        }

        $value = strtoupper(trim($value));

        return $value === 'PRO' ? 'PRO' : 'FREE';
    }

    public function isConfigured(): bool
    {
        return $this->getApiKey() !== null;
    }
}
