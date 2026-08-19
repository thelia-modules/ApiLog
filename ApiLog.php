<?php

declare(strict_types=1);

namespace ApiLog;

use ApiLog\Extension\ApiLogExtension;
use ApiLog\Logger\CustomLogger;
use ApiLog\Service\HttpClientLogging;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Core\Install\Database;
use Thelia\Module\BaseModule;

class ApiLog extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'apilog';

    public static function loadConfiguration(ContainerBuilder $containerBuilder): void
    {
        $extension = new ApiLogExtension();
        $containerBuilder->registerExtension($extension);
        $extension->load([
            'channels' => ['api_log'],
            'handlers' => [
                'api_log' => [
                    'type' => 'rotating_file',
                    'max_files' => 10,
                    'level' => 'info',
                    'path' => '%kernel.logs_dir%/api_log.log',
                    'channels' => ['api_log'],
                ],
            ],
        ], $containerBuilder);
    }

    /**
     * Defines how services are loaded in your modules
     *
     * @param ServicesConfigurator $servicesConfigurator
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n',
                __DIR__.'/Config',
                __DIR__.'/Tests',
                __FILE__,
            ])
            ->autowire(true)
            ->autoconfigure(true);

        $servicesConfigurator->set(HttpClientLogging::class, HttpClientLogging::class)
            ->decorate(HttpClientInterface::class)
            ->args([
                new Reference(HttpClientLogging::class.'.inner'),
                new Reference(CustomLogger::class),
            ]);
    }

    /**
     * Execute sql files in Config/update/ folder named with module version (ex: 1.0.1.sql).
     *
     * @param $currentVersion
     * @param $newVersion
     * @param ConnectionInterface $con
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $updateDir = __DIR__.DS.'Config'.DS.'update';

        if (! is_dir($updateDir)) {
            return;
        }

        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in($updateDir);

        $database = new Database($con);

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }
}
