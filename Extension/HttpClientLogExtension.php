<?php

namespace ApiLog\Extension;

use ApiLog\Logger\HttpClientLogger;
use ApiLog\Service\LoggingHttpClient;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;

class HttpClientLogExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        // === 1. Créer le handler Monolog avec rotation ===
        $handlerDefinition = new Definition(RotatingFileHandler::class);
        $handlerDefinition->setArguments([
            '%kernel.logs_dir%/http_client.log',
            10,
            Logger::INFO,
        ]);
        $handlerDefinition->addTag('monolog.handler', ['channel' => 'http_client']);

        $container->setDefinition('http_logger.rotating_handler', $handlerDefinition);

        // === 2. Définir le service HttpClientLogger ===
        $loggerDefinition = new Definition(HttpClientLogger::class);
        $loggerDefinition->setAutowired(true)->setAutoconfigured(true);
        $loggerDefinition->setPublic(true);
        $container->setDefinition('http_logger.client_logger', $loggerDefinition);

        // === 3. Définir le client décorateur ===
        $decoratorDefinition = new Definition(LoggingHttpClient::class);
        $decoratorDefinition->setDecoratedService('http_client');
        $decoratorDefinition->setAutowired(true)->setAutoconfigured(true);
        $decoratorDefinition->setPublic(true);
        $container->setDefinition('http_logger.logging_http_client', $decoratorDefinition);
    }
}
