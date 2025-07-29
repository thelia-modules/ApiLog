<?php

namespace ApiLog\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class ApiLogExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('monolog', $configs);
    }
}
