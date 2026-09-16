#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * FluxBB Console — CLI entry point for migrations and maintenance.
 *
 * Usage:
 *   php console.php migrations:migrate
 *   php console.php migrations:status
 *   php console.php fluxbb:import --old-db-url=...
 *   php console.php cache:warmup
 *
 * @see https://www.doctrine-project.org/projects/doctrine-migrations/en/3.7/reference/introduction.html
 */

use FluxBB\Shared\Infrastructure\Cache\CacheWarmer;
use FluxBB\Shared\Infrastructure\Database\DoctrineConnectionFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

(static function () {
    define('FLUXBB_ROOT', __DIR__);

    $autoloader = require FLUXBB_ROOT . '/vendor/autoload.php';

    // Load .env
    if (file_exists(FLUXBB_ROOT . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(FLUXBB_ROOT);
        $dotenv->load();
    }

    // Bootstrap DI container
    $builder = new \DI\ContainerBuilder();
    $builder->addDefinitions(FLUXBB_ROOT . '/config/config.php');
    $builder->addDefinitions(FLUXBB_ROOT . '/config/services.php');
    $builder->useAutowiring(true);
    $container = $builder->build();

    $application = new Application('FluxBB Console', '1.0.0');

    // --- Built-in: cache:warmup ---
    $application->register('cache:warmup')
        ->setDescription('Warm up application caches (bans, config)')
        ->setCode(function (InputInterface $input, OutputInterface $output) use ($container): int {
            $output->writeln('<info>Warming up caches...</info>');

            /** @var CacheWarmer $warmer */
            $warmer = $container->get(CacheWarmer::class);
            $results = $warmer->warmAll();

            foreach ($results as $name => $success) {
                $status = $success ? '<fg=green>OK</>' : '<fg=red>FAIL</>';
                $output->writeln("  - {$name}: {$status}");
            }

            $output->writeln('<info>Done.</info>');
            return Command::SUCCESS;
        });

    // --- Delegate to Doctrine Migrations ---
    /** @var \Doctrine\DBAL\Connection $connection */
    $connection = $container->get(\Doctrine\DBAL\Connection::class);

    // Use Doctrine Migrations ConfigurationLoader API
    $configArray = [
        'migrations_paths' => [
            'FluxBB\Migrations' => FLUXBB_ROOT . '/migrations',
        ],
        'table_storage' => [
            'table_name' => 'doctrine_migration_versions',
            'version_column_name' => 'version',
            'version_column_length' => 191,
        ],
        'organize_migrations' => 'year',
    ];

    $configurationLoader = new \Doctrine\Migrations\Configuration\Migration\ConfigurationArray($configArray);
    $connectionLoader = new \Doctrine\Migrations\Configuration\Connection\ExistingConnection($connection);

    $dependencyFactory = \Doctrine\Migrations\DependencyFactory::fromConnection($configurationLoader, $connectionLoader);
    $application->addCommands([
        new \Doctrine\Migrations\Tools\Console\Command\MigrateCommand($dependencyFactory),
        new \Doctrine\Migrations\Tools\Console\Command\StatusCommand($dependencyFactory),
        new \Doctrine\Migrations\Tools\Console\Command\DiffCommand($dependencyFactory),
        new \Doctrine\Migrations\Tools\Console\Command\ExecuteCommand($dependencyFactory),
        new \Doctrine\Migrations\Tools\Console\Command\GenerateCommand($dependencyFactory),
    ]);

    // --- Import command ---
    $application->add($container->get(\FluxBB\Migrations\ImportCommand::class));

    $application->run();
})();