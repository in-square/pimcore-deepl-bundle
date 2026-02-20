<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('in_square_pimcore_deepl');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('overwrite')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('documents')->defaultFalse()->end()
                        ->booleanNode('objects')->defaultFalse()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
