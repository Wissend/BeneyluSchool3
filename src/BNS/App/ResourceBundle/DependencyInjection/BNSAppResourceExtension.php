<?php

namespace BNS\App\ResourceBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BNSAppResourceExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if(!$container->hasParameter('bns_resource_storage'))
        {
            $container->setParameter('bns_resource_storage',$config['default_adapter']);

        }

        $fileSystemService = $container->getDefinition('bns.file_system_manager');
        $fileSystemService->addArgument($container->getDefinition('bns.' . $container->getParameter('bns_resource_storage') .'.adapter'));

    }
}
