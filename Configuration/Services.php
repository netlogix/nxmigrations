<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Netlogix\Migrations\Service\DependencyFactory;
use Netlogix\Migrations\Service\DoctrineService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

return function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Netlogix\\Migrations\\', '../Classes/');

    $services->set('connection.migrations')
        ->class(Connection::class)
        ->factory([service(ConnectionPool::class), 'getConnectionForTable'])
        ->args([DependencyFactory::DOCTRINE_MIGRATIONSTABLENAME]);

    $services->set(DoctrineService::class)
        ->arg('$connection', service('connection.migrations'));
};
