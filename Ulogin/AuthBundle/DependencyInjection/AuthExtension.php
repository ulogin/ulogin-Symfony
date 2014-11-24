<?php

namespace Ulogin\AuthBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AuthExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new \Ulogin\AuthBundle\DependencyInjection\Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->setRecursive($container, null, $config);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
        $loader->load('parameters.xml');
    }

    private function setRecursive(ContainerBuilder $container, $key, $value){
        if(is_array($value)){
            foreach($value as $key => $val){
                $this->setRecursive($container, $key, $val);
            }
        } else {
            $container->setParameter("ulogin_auth.$key", $value);
        }
    }
}
