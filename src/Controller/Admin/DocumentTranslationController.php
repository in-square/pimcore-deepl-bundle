<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\Controller\Admin;

use InSquare\PimcoreDeeplBundle\Exception\DeeplApiException;
use InSquare\PimcoreDeeplBundle\Exception\DeeplConfigurationException;
use InSquare\PimcoreDeeplBundle\Service\DeeplClient;
use InSquare\PimcoreDeeplBundle\Service\TextValueHelper;
use InSquare\PimcoreDeeplBundle\Service\TranslationConfig;
use Pimcore\Bundle\AdminBundle\Controller\AdminAbstractController;
use Pimcore\Db;
use Pimcore\Model\Document;
use Pimcore\Model\Document\PageSnippet;
use Pimcore\Model\Document\Service as DocumentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class DocumentTranslationController extends AdminAbstractController
{
    #[Route('/document/status', name: 'insquare_pimcore_deepl_document_status', methods: ['GET'])]
    public function statusAction(Request $request): JsonResponse
    {
        $this->checkPermission('documents');

        $documentId = (int) $request->get('id');

        $document = Document::getById($documentId);
        if (!$document instanceof PageSnippet) {
            return $this->adminJson(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $documentService = new DocumentService();
        $translationSourceId = (int) $documentService->getTranslationSourceId($document);
        $isTranslation = $translationSourceId !== $documentId || (bool) $document->getContentMainDocumentId();

        return $this->adminJson([
            'success' => true,
            'isTranslation' => $isTranslation,
        ]);
    }

    #[Route('/document/tasks', name: 'insquare_pimcore_deepl_document_tasks', methods: ['GET'])]
    public function tasksAction(Request $request, TextValueHelper $textHelper, TranslationConfig $translationConfig): JsonResponse
    {
        $this->checkPermission('documents');

        $documentId = (int) $request->get('id');
        $scope = (string) $request->get('scope', 'document');
        $prefix = (string) $request->get('brickPrefix', '');
        $skipIfTargetNotEmpty = $request->query->getBoolean('skipIfTargetNotEmpty', true);
        if ($translationConfig->overwriteDocuments()) {
            $skipIfTargetNotEmpty = false;
        }

        $document = Document::getById($documentId);
        if (!$document instanceof PageSnippet) {
            return $this->adminJson(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $documentService = new DocumentService();
        $translationSourceId = (int) $documentService->getTranslationSourceId($document);
        $sourceDocId = $translationSourceId !== $documentId
            ? $translationSourceId
            : ($document->getContentMainDocumentId() ?: $documentId);
        $sourceDoc = Document::getById((int) $sourceDocId);
        if (!$sourceDoc instanceof PageSnippet) {
            return $this->adminJson(['success' => false, 'message' => 'Source document not found.'], 404);
        }
        $sourceDataDocumentId = (int) $sourceDocId;

        $sourceLang = (string) $sourceDoc->getProperty('language');
        $targetLang = (string) $document->getProperty('language');
        $isTranslation = $translationSourceId !== $documentId || (bool) $document->getContentMainDocumentId();

        if ($sourceLang === '' || $targetLang === '') {
            return $this->adminJson(['success' => false, 'message' => 'Missing language information.'], 400);
        }

        $db = Db::get();
        $types = ['input', 'textarea', 'wysiwyg'];

        if ($scope === 'brick') {
            if ($prefix === '') {
                return $this->adminJson(['success' => false, 'message' => 'Missing brick prefix.'], 400);
            }

            $hasOverride = (bool) $db->fetchOne(
                'SELECT 1 FROM documents_editables WHERE documentId = ? AND name LIKE ? LIMIT 1',
                [$documentId, $prefix . '%']
            );

            if (!$hasOverride) {
                return $this->adminJson([
                    'success' => true,
                    'overridden' => false,
                    'tasks' => [],
                    'sourceLang' => $sourceLang,
                    'targetLang' => $targetLang,
                    'isTranslation' => $isTranslation,
                    'sourceDocumentId' => (int) $sourceDocId,
                ]);
            }
        }

        $params = [$sourceDocId];
        $where = 'documentId = ? AND type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
        $params = array_merge($params, $types);

        if ($prefix !== '') {
            $where .= ' AND name LIKE ?';
            $params[] = $prefix . '%';
        }

        $rows = $db->fetchAllAssociative(
            'SELECT name, type, data FROM documents_editables WHERE ' . $where,
            $params
        );

        if ($rows === []) {
            $fallbackId = $sourceDoc->getContentMainDocumentId();
            if ($fallbackId && $fallbackId !== $sourceDataDocumentId) {
                $fallbackParams = [$fallbackId];
                $fallbackParams = array_merge($fallbackParams, $types);
                if ($prefix !== '') {
                    $fallbackParams[] = $prefix . '%';
                }

                $fallbackRows = $db->fetchAllAssociative(
                    'SELECT name, type, data FROM documents_editables WHERE ' . $where,
                    $fallbackParams
                );

                if ($fallbackRows !== []) {
                    $rows = $fallbackRows;
                    $sourceDataDocumentId = (int) $fallbackId;
                }
            }
        }

        $tasks = [];
        foreach ($rows as $row) {
            if ($textHelper->isEmpty($row['data'] ?? null)) {
                continue;
            }

            if ($skipIfTargetNotEmpty) {
                $targetData = $db->fetchOne(
                    'SELECT data FROM documents_editables WHERE documentId = ? AND name = ? LIMIT 1',
                    [$documentId, $row['name']]
                );

                if (
                    !$textHelper->isEmpty($targetData)
                    && (string) $targetData !== (string) ($row['data'] ?? '')
                ) {
                    continue;
                }
            }

            $tasks[] = [
                'name' => $row['name'],
                'type' => $row['type'],
            ];
        }

        return $this->adminJson([
            'success' => true,
            'overridden' => true,
            'tasks' => $tasks,
            'sourceLang' => $sourceLang,
            'targetLang' => $targetLang,
            'isTranslation' => $isTranslation,
            'sourceDocumentId' => (int) $sourceDataDocumentId,
        ]);
    }

    #[Route('/document/translate-field', name: 'insquare_pimcore_deepl_document_translate_field', methods: ['POST'])]
    public function translateFieldAction(
        Request $request,
        DeeplClient $deeplClient,
        TextValueHelper $textHelper,
        TranslationConfig $translationConfig
    ): JsonResponse {
        $this->checkPermission('documents');

        $documentId = (int) $request->get('id');
        $name = (string) $request->get('name');
        $sourceLang = (string) $request->get('sourceLang');
        $targetLang = (string) $request->get('targetLang');
        $sourceDocumentId = (int) $request->get('sourceDocumentId');
        $skipIfTargetNotEmpty = $request->request->getBoolean('skipIfTargetNotEmpty', true);
        if ($translationConfig->overwriteDocuments()) {
            $skipIfTargetNotEmpty = false;
        }

        if ($documentId <= 0 || $name === '' || $targetLang === '') {
            return $this->adminJson(['success' => false, 'message' => 'Missing required parameters.'], 400);
        }

        $document = Document::getById($documentId);
        if (!$document instanceof PageSnippet) {
            return $this->adminJson(['success' => false, 'message' => 'Document not found.'], 404);
        }

        if ($sourceDocumentId <= 0) {
            $documentService = new DocumentService();
            $translationSourceId = (int) $documentService->getTranslationSourceId($document);
            $sourceDocumentId = $translationSourceId !== $document->getId()
                ? $translationSourceId
                : ($document->getContentMainDocumentId() ?: $document->getId());
        }

        $db = Db::get();
        $sourceRow = $db->fetchAssociative(
            'SELECT type, data FROM documents_editables WHERE documentId = ? AND name = ? LIMIT 1',
            [$sourceDocumentId, $name]
        );

        if (!$sourceRow && $document instanceof PageSnippet) {
            $fallbackId = $document->getContentMainDocumentId();
            if ($fallbackId && $fallbackId !== $sourceDocumentId) {
                $sourceRow = $db->fetchAssociative(
                    'SELECT type, data FROM documents_editables WHERE documentId = ? AND name = ? LIMIT 1',
                    [$fallbackId, $name]
                );
                if ($sourceRow) {
                    $sourceDocumentId = (int) $fallbackId;
                }
            }
        }

        if (!$sourceRow || $textHelper->isEmpty($sourceRow['data'] ?? null)) {
            return $this->adminJson(['success' => true, 'skipped' => true, 'reason' => 'empty_source']);
        }

        if ($skipIfTargetNotEmpty) {
            $targetData = $db->fetchOne(
                'SELECT data FROM documents_editables WHERE documentId = ? AND name = ? LIMIT 1',
                [$documentId, $name]
            );
            if (
                !$textHelper->isEmpty($targetData)
                && (string) $targetData !== (string) ($sourceRow['data'] ?? '')
            ) {
                return $this->adminJson(['success' => true, 'skipped' => true, 'reason' => 'already_filled']);
            }
        }

        $isHtml = ($sourceRow['type'] ?? '') === 'wysiwyg';

        try {
            $translated = $deeplClient->translate((string) $sourceRow['data'], $sourceLang ?: null, $targetLang, $isHtml);
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

        $document->getEditables();
        $document->setEditables($document->getEditables());
        $document->setRawEditable($name, $sourceRow['type'], $translated);
        $document->save();

        return $this->adminJson(['success' => true, 'skipped' => false]);
    }
}
