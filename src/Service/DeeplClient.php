<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\Service;

use GuzzleHttp\ClientInterface;
use InSquare\PimcoreDeeplBundle\Exception\DeeplApiException;
use InSquare\PimcoreDeeplBundle\Exception\DeeplConfigurationException;

final class DeeplClient
{
    private const FREE_ENDPOINT = 'https://api-free.deepl.com/v2/translate';
    private const PRO_ENDPOINT = 'https://api.deepl.com/v2/translate';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly DeeplSettings $settings
    ) {
    }

    public function translate(string $text, ?string $sourceLang, string $targetLang, bool $isHtml): string
    {
        $apiKey = $this->settings->getApiKey();
        if ($apiKey === null) {
            throw new DeeplConfigurationException('Missing DeepL API key.');
        }

        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $endpoint = $this->settings->getAccountType() === 'PRO'
            ? self::PRO_ENDPOINT
            : self::FREE_ENDPOINT;

        $params = [
            'text' => $text,
            'target_lang' => strtoupper($targetLang),
        ];

        if ($sourceLang) {
            $params['source_lang'] = strtoupper($sourceLang);
        }

        if ($isHtml) {
            $params['tag_handling'] = 'html';
        }

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
                ],
                'form_params' => $params,
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            throw new DeeplApiException('DeepL API request failed: ' . $exception->getMessage());
        }

        $status = $response->getStatusCode();
        $content = (string) $response->getBody();

        if ($status >= 400) {
            $message = 'DeepL API error.';
            $payload = json_decode($content, true);
            if (is_array($payload) && !empty($payload['message'])) {
                $message = (string) $payload['message'];
            }

            throw new DeeplApiException($message, $status);
        }

        $payload = json_decode($content, true);
        if (!is_array($payload) || empty($payload['translations'][0]['text'])) {
            throw new DeeplApiException('Invalid DeepL response.', $status);
        }

        return (string) $payload['translations'][0]['text'];
    }
}
