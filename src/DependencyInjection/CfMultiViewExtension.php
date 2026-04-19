<?php

namespace Michallkanak\SymfonyCloudflareMultiView\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CfMultiViewExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('cf_multi_view.accounts', $config['accounts']);
        $container->setParameter('cf_multi_view.dashboard_password', $config['dashboard_password']);
        $container->setParameter('cf_multi_view.secure_dashboard', $config['secure_dashboard']);
        $container->setParameter('cf_multi_view.cache_type', $config['cache']['type']);
        $container->setParameter('cf_multi_view.cache_dsn', $config['cache']['dsn']);
        $container->setParameter('cf_multi_view.timezone', $config['timezone']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }
}
