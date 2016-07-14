<?php

namespace Chteuchteu\SymExc2GtlbIsuBndle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('sym_exc_2_gtlb_isu_bndle');

        $rootNode
            ->children()
                ->scalarNode('gitlab_api_url')
                    ->defaultValue('https://gitlab.com/api/v3/')
                ->end()
                ->scalarNode('gitlab_token')
                    ->isRequired()
                    ->defaultNull()
                ->end()
                ->scalarNode('project')
                    ->isRequired()
                    ->defaultNull()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
