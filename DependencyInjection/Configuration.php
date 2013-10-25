<?php

namespace Main\CasRestBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('main_cas_rest');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.
        
        $rootNode
            ->children()
                ->scalarNode('cas_rest_url')->defaultValue("")->end()
                ->scalarNode('cas_service_url')->defaultValue('')->end()
                ->scalarNode('service_url')->defaultValue('')->end()
                ->scalarNode('cas_cert')->defaultValue('')->end()
                ->scalarNode('cas_local')->defaultValue('')->end()
                ->scalarNode('source_dn')->defaultValue('')->end()
                ->scalarNode('base_dn')->defaultValue('')->end()      
            ->end();

        return $treeBuilder;
    }
}
