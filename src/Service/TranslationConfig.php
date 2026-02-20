<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\Service;

final class TranslationConfig
{
    public function __construct(
        private bool $overwriteDocuments = false,
        private bool $overwriteObjects = false
    ) {
    }

    public function overwriteDocuments(): bool
    {
        return $this->overwriteDocuments;
    }

    public function overwriteObjects(): bool
    {
        return $this->overwriteObjects;
    }
}
