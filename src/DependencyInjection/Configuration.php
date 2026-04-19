<?php

namespace Michallkanak\SymfonyCloudflareMultiView\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('cf_multi_view');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $nodeBuilder = $rootNode->children();

        $nodeBuilder->scalarNode('dashboard_password')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Password to protect the dashboard view')
        ->end();

        $nodeBuilder->booleanNode('secure_dashboard')
            ->defaultTrue()
            ->info('When false, dashboard is accessible without a password')
        ->end();

        $nodeBuilder->scalarNode('timezone')
            ->defaultValue('UTC')
            ->info('Timezone for displaying data (e.g. Europe/Warsaw)')
        ->end();

        $accountsNode = $nodeBuilder->arrayNode('accounts')
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->info('List of Cloudflare accounts. Each account becomes a group on the dashboard.');

        /** @var \Symfony\Component\Config\Definition\Builder\NodeBuilder $accountsBuilder */
        $accountsBuilder = $accountsNode->arrayPrototype()->children();

        $accountsBuilder->scalarNode('name')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Display name for this account (used as group name on the dashboard)');

        $accountsBuilder->scalarNode('token')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Cloudflare API Token with Analytics:Read and Zone.Zone:Read permissions');

        $cacheNodeBuilder = $nodeBuilder->arrayNode('cache')
            ->addDefaultsIfNotSet()
            ->children();

        $cacheNodeBuilder->enumNode('type')
            ->values(['filesystem', 'redis'])
            ->defaultValue('filesystem')
        ->end();

        $cacheNodeBuilder->scalarNode('dsn')
            ->defaultNull()
            ->info('Redis DSN, e.g. redis://localhost:6379')
        ->end();

        return $treeBuilder;
    }
}
