<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class InSquarePimcoreDeeplExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('in_square_pimcore_deepl.overwrite.documents', (bool) $config['overwrite']['documents']);
        $container->setParameter('in_square_pimcore_deepl.overwrite.objects', (bool) $config['overwrite']['objects']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
