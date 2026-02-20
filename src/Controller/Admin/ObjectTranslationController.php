<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\Controller\Admin;

use InSquare\PimcoreDeeplBundle\Exception\DeeplApiException;
use InSquare\PimcoreDeeplBundle\Exception\DeeplConfigurationException;
use InSquare\PimcoreDeeplBundle\Service\DeeplClient;
use InSquare\PimcoreDeeplBundle\Service\TextValueHelper;
use InSquare\PimcoreDeeplBundle\Service\TranslationConfig;
use Pimcore\Bundle\AdminBundle\Controller\AdminAbstractController;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Classificationstore\Service as ClassificationstoreService;
use Pimcore\Model\DataObject\ClassDefinition\Data\Block;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Textarea;
use Pimcore\Model\DataObject\ClassDefinition\Data\Wysiwyg;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\BlockElement;
use Pimcore\Model\DataObject\Fieldcollection;
use Pimcore\Model\DataObject\Localizedfield;
use Pimcore\Model\DataObject\Objectbrick;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class ObjectTranslationController extends AdminAbstractController
{
    #[Route('/object/translate-field', name: 'insquare_pimcore_deepl_object_translate_field', methods: ['POST'])]
    public function translateFieldAction(
        Request $request,
        DeeplClient $deeplClient,
        TextValueHelper $textHelper,
        TranslationConfig $translationConfig
    ): JsonResponse {
        $this->checkPermission('objects');

        $id = (int) $request->get('id');
        $sourceLang = (string) $request->get('source');
        $targetLang = (string) $request->get('target');
        $key = (string) $request->get('key');
        $text = (string) $request->get('text');

        if ($id <= 0 || $key === '' || $targetLang === '') {
            return $this->adminJson(['success' => false, 'message' => 'Missing required parameters.'], 400);
        }

        $object = DataObject::getById($id);
        if (!$object instanceof Concrete) {
            return $this->adminJson(['success' => false, 'message' => 'Object not found.'], 404);
        }

        if ($textHelper->isEmpty($text)) {
            return $this->adminJson(['success' => true, 'skipped' => true, 'reason' => 'empty_source']);
        }

        try {
            $result = $this->translateObjectField(
                $object,
                $key,
                $text,
                $sourceLang,
                $targetLang,
                $deeplClient,
                $textHelper,
                $translationConfig->overwriteObjects()
            );
        } catch (DeeplConfigurationException $exception) {
            return $this->adminJson(['success' => false, 'message' => $exception->getMessage(), 'code' => 'missing_key'], 400);
        } catch (DeeplApiException $exception) {
            return $this->adminJson([
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => 'deepl_error',
                'status' => $exception->getStatusCode(),
            ], 502);
        } catch (\Throwable $exception) {
            return $this->adminJson(['success' => false, 'message' => 'Translation failed.'], 500);
        }

        return $this->adminJson($result);
    }

    private function translateObjectField(
        Concrete $object,
        string $key,
        string $text,
        string $sourceLang,
        string $targetLang,
        DeeplClient $deeplClient,
        TextValueHelper $textHelper,
        bool $allowOverwrite
    ): array {
        if (str_starts_with($key, 'structuredData#')) {
            return $this->translateStructuredData(
                $object,
                $key,
                $text,
                $sourceLang,
                $targetLang,
                $deeplClient,
                $textHelper,
                $allowOverwrite
            );
        }

        if (str_starts_with($key, 'classificationStore#')) {
            return $this->translateClassificationStore(
                $object,
                $key,
                $text,
                $sourceLang,
                $targetLang,
                $deeplClient,
                $textHelper,
                $allowOverwrite
            );
        }

        $definition = $this->getLocalizedFieldDefinition($object, $key);
        if (!$definition || !$this->isAllowedDefinition($definition)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
        }

        $currentValue = $object->get($key, $targetLang);
        if (!$allowOverwrite && !$textHelper->isEmpty($currentValue)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'already_filled'];
        }

        $translated = $deeplClient->translate($text, $sourceLang ?: null, $targetLang, $definition instanceof Wysiwyg);

        $object->set($key, $translated, $targetLang);
        $object->save();

        return ['success' => true, 'skipped' => false];
    }

    private function translateStructuredData(
        Concrete $object,
        string $key,
        string $text,
        string $sourceLang,
        string $targetLang,
        DeeplClient $deeplClient,
        TextValueHelper $textHelper,
        bool $allowOverwrite
    ): array {
        $parts = explode('.', $key);
        if (count($parts) < 5) {
            return ['success' => true, 'skipped' => true, 'reason' => 'invalid_key'];
        }

        [, $fieldName, $index, $type, $field] = $parts;

        $structuredField = $object->get($fieldName);

        if ($structuredField instanceof Fieldcollection) {
            $structuredField->setObject($object);
            $item = $structuredField->get((int) $index);
            if (!$item) {
                return ['success' => true, 'skipped' => true, 'reason' => 'missing_item'];
            }

            $definition = $item->getDefinition()?->getFieldDefinition($field);
            if (!$definition || !$this->isAllowedDefinition($definition)) {
                return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
            }

            $currentValue = $item->get($field, $targetLang);
            if (!$allowOverwrite && !$textHelper->isEmpty($currentValue)) {
                return ['success' => true, 'skipped' => true, 'reason' => 'already_filled'];
            }

            $translated = $deeplClient->translate($text, $sourceLang ?: null, $targetLang, $definition instanceof Wysiwyg);
            $item->set($field, $translated, $targetLang);
            $object->save();

            return ['success' => true, 'skipped' => false];
        }

        if ($structuredField instanceof Objectbrick) {
            $structuredField->setObject($object);
            $brick = $structuredField->get($type);
            if (!$brick) {
                return ['success' => true, 'skipped' => true, 'reason' => 'missing_item'];
            }

            $definition = $brick->getDefinition()?->getFieldDefinition($field);
            if (!$definition || !$this->isAllowedDefinition($definition)) {
                return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
            }

            $currentValue = $brick->get($field, $targetLang);
            if (!$allowOverwrite && !$textHelper->isEmpty($currentValue)) {
                return ['success' => true, 'skipped' => true, 'reason' => 'already_filled'];
            }

            $translated = $deeplClient->translate($text, $sourceLang ?: null, $targetLang, $definition instanceof Wysiwyg);
            $brick->set($field, $translated, $targetLang);
            $object->save();

            return ['success' => true, 'skipped' => false];
        }

        if (is_array($structuredField) && $type === 'undefined') {
            $blockDefinition = $object->getClass()?->getFieldDefinition($fieldName);
            if (!$blockDefinition instanceof Block) {
                return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
            }

            $localizedDefinition = $blockDefinition->getFieldDefinition('localizedfields');
            if (!$localizedDefinition instanceof Localizedfields) {
                return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
            }

            $definition = $localizedDefinition->getFieldDefinition($field, ['object' => $object]);
            if (!$definition || !$this->isAllowedDefinition($definition)) {
                return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
            }

            $blockItem = $structuredField[(int) $index] ?? null;
            if (!is_array($blockItem) || !isset($blockItem['localizedfields'])) {
                return ['success' => true, 'skipped' => true, 'reason' => 'missing_item'];
            }

            $localizedContainer = $blockItem['localizedfields'];
            $localizedField = null;
            if ($localizedContainer instanceof BlockElement) {
                $localizedField = $localizedContainer->getData();
            } elseif ($localizedContainer instanceof Localizedfield) {
                $localizedField = $localizedContainer;
            }

            if (!$localizedField instanceof Localizedfield) {
                return ['success' => true, 'skipped' => true, 'reason' => 'missing_item'];
            }

            $currentValue = $localizedField->getLocalizedValue($field, $targetLang);
            if (!$allowOverwrite && !$textHelper->isEmpty($currentValue)) {
                return ['success' => true, 'skipped' => true, 'reason' => 'already_filled'];
            }

            $translated = $deeplClient->translate($text, $sourceLang ?: null, $targetLang, $definition instanceof Wysiwyg);
            $localizedField->setLocalizedValue($field, $translated, $targetLang);

            if ($localizedContainer instanceof BlockElement) {
                $localizedContainer->setData($localizedField);
                $blockItem['localizedfields'] = $localizedContainer;
                $structuredField[(int) $index] = $blockItem;
                $object->set($fieldName, $structuredField);
            }

            $object->save();

            return ['success' => true, 'skipped' => false];
        }

        return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
    }

    private function translateClassificationStore(
        Concrete $object,
        string $key,
        string $text,
        string $sourceLang,
        string $targetLang,
        DeeplClient $deeplClient,
        TextValueHelper $textHelper,
        bool $allowOverwrite
    ): array {
        $parts = explode('.', $key);
        if (count($parts) < 4) {
            return ['success' => true, 'skipped' => true, 'reason' => 'invalid_key'];
        }

        [, $groupId, $fieldName, $keyId] = $parts;
        $groupId = (int) $groupId;
        $keyId = (int) $keyId;

        $store = $object->get($fieldName);
        if (!$store instanceof Classificationstore) {
            return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
        }

        $keyConfig = DefinitionCache::get($keyId);
        $definition = ClassificationstoreService::getFieldDefinitionFromKeyConfig($keyConfig);
        if (!$definition || !$this->isAllowedDefinition($definition)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_field'];
        }

        $currentValue = $store->getLocalizedKeyValue($groupId, $keyId, $targetLang, true, true);
        if (!$allowOverwrite && !$textHelper->isEmpty($currentValue)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'already_filled'];
        }

        $translated = $deeplClient->translate($text, $sourceLang ?: null, $targetLang, $definition instanceof Wysiwyg);
        $store->setObject($object);
        $store->setLocalizedKeyValue($groupId, $keyId, $translated, $targetLang);
        $object->save();

        return ['success' => true, 'skipped' => false];
    }

    private function getLocalizedFieldDefinition(Concrete $object, string $fieldName): ?\Pimcore\Model\DataObject\ClassDefinition\Data
    {
        $localized = $object->getClass()?->getFieldDefinition('localizedfields');
        if (!$localized instanceof Localizedfields) {
            return null;
        }

        return $localized->getFieldDefinition($fieldName, ['object' => $object]);
    }

    private function isAllowedDefinition(?\Pimcore\Model\DataObject\ClassDefinition\Data $definition): bool
    {
        if (!$definition) {
            return false;
        }

        return $definition instanceof Input
            || $definition instanceof Textarea
            || $definition instanceof Wysiwyg;
    }
}
