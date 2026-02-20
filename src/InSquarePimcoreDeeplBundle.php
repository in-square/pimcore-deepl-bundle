<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle;

use InSquare\PimcoreDeeplBundle\DependencyInjection\InSquarePimcoreDeeplExtension;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class InSquarePimcoreDeeplBundle extends AbstractPimcoreBundle implements PimcoreBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;

    public function getJsPaths(): array
    {
        return [
            '/bundles/insquarepimcoredeepl/js/util/progress.js',
            '/bundles/insquarepimcoredeepl/js/object/translate.js',
            '/bundles/insquarepimcoredeepl/js/document/translate.js',
            '/bundles/insquarepimcoredeepl/js/pimcore/startup.js',
        ];
    }

    public function getEditmodeJsPaths(): array
    {
        return [
            '/bundles/insquarepimcoredeepl/js/util/progress.js',
            '/bundles/insquarepimcoredeepl/js/document/translate.js',
            '/bundles/insquarepimcoredeepl/js/document/areablock.js',
            '/bundles/insquarepimcoredeepl/js/document/block.js',
        ];
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new InSquarePimcoreDeeplExtension();
        }

        return $this->extension;
    }
}
