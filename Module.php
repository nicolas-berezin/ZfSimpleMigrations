<?php
namespace ZfSimpleMigrations;

use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\EventManager\EventInterface;
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    ConsoleUsageProviderInterface,
    ServiceProviderInterface,
    BootstrapListenerInterface
{
    /**
     * @param EventInterface|\Zend\Mvc\MvcEvent $e
     * @return array|void
     */
    public function onBootstrap(EventInterface $e)
    {
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    public function getServiceConfig()
    {
        return [
            'abstract_factories' => [
                'ZfSimpleMigrations\\Library\\MigrationAbstractFactory',
                'ZfSimpleMigrations\\Model\\MigrationVersionTableAbstractFactory',
                'ZfSimpleMigrations\\Model\\MigrationVersionTableGatewayAbstractFactory',
                'ZfSimpleMigrations\\Library\\MigrationSkeletonGeneratorAbstractFactory'
            ],
        ];
    }

    public function getConsoleUsage(Console $console)
    {
        return [
            'Initialize migrations (create DB table e.t.c.)',
            'migration init' => '',

            'Get last applied migration version',
            'migration version [<source>] [<name>]' => '',
            ['[<source>]', 'specify which migrations data source to use, defaults to use all sources'],
            ['[<name>]', 'specify which configured migrations to run, defaults to `default`'],

            'List available migrations',
            'migration list [<source>] [<name>] [--all]' => '',
            ['--all', 'Include applied migrations'],
            ['[<source>]', 'specify which migrations data source to use, defaults to use all sources'],
            ['[<name>]', 'specify which configured migrations to run, defaults to `default`'],

            'Generate new migration skeleton class',
            'migration generate [<source>] [<name>]' => '',
            ['[<source>]', 'specify which migrations data source to use, defaults to use all sources'],
            ['[<name>]', 'specify which configured migrations to run, defaults to `default`'],

            'Execute migration',
            'migration apply [<source>] [<name>] [<version>] [--force] [--down] [--fake]' => '',
            ['[<source>]', 'specify which migrations data source to use, defaults to use all sources'],
            ['[<name>]', 'specify which configured migrations to run, defaults to `default`'],
            [
                '--force',
                'Force apply migration even if it\'s older than the last migrated. Works only with <version> explicitly set.'
            ],
            [
                '--down',
                'Force apply down migration. Works only with --force flag set.'
            ],
            [
                '--fake',
                'Fake apply or apply down migration. Adds/removes migration to the list of applied w/out really applying it. Works only with <version> explicitly set.'
            ],
        ];
    }
}
