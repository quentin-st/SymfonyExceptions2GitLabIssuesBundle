<?php

namespace Chteuchteu\SymExc2GtlbIsuBndle\DependencyInjection;

use Chteuchteu\SymExc2GtlbIsuBndle\SymfonyExceptions2GitLabIssuesBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class SymfonyExceptions2GitLabIssuesExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        // Configuration
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Services
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $container->setParameter($this->getAlias().'.gitlab_api_url', $config['gitlab_api_url']);
        $container->setParameter($this->getAlias().'.gitlab_token', $config['gitlab_token']);
        $container->setParameter($this->getAlias().'.project', $config['project']);
        $container->setParameter($this->getAlias().'.reopen_closed_issues', $config['reopen_closed_issues']);
        $container->setParameter($this->getAlias().'.excluded_environments', $config['excluded_environments']);
        $container->setParameter($this->getAlias().'.excluded_exceptions', $config['excluded_exceptions']);
        $container->setParameter($this->getAlias().'.mentions', $config['mentions']);
    }

    public function getAlias()
    {
        return SymfonyExceptions2GitLabIssuesBundle::DI_Alias;
    }
}
