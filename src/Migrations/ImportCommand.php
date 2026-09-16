<?php

declare(strict_types=1);

namespace FluxBB\Migrations;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command: import legacy FluxBB 1.5 database into the new schema.
 *
 * Usage:
 *   php console.php fluxbb:import \
 *     --old-db-url=pdo_mysql://user:pass@host:port/old_fluxbb \
 *     --prefix=forum_
 *
 * This command creates a read-only connection to the old database and
 * transforms all data to the new FluxBB Next schema.
 */
#[AsCommand(
    name: 'fluxbb:import',
    description: 'Import data from a FluxBB 1.5 legacy database',
)]
class ImportCommand extends Command
{
    public function __construct(
        private readonly Connection $targetConnection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('old-db-url', null, InputOption::VALUE_REQUIRED, 'Doctrine DBAL URL for the old FluxBB 1.5 database')
            ->addOption('old-driver', null, InputOption::VALUE_REQUIRED, 'Old DB driver (pdo_mysql, pdo_pgsql, pdo_sqlite)', 'pdo_mysql')
            ->addOption('old-host', null, InputOption::VALUE_REQUIRED, 'Old DB host', '127.0.0.1')
            ->addOption('old-port', null, InputOption::VALUE_REQUIRED, 'Old DB port', '3306')
            ->addOption('old-dbname', null, InputOption::VALUE_REQUIRED, 'Old DB name', 'fluxbb')
            ->addOption('old-user', null, InputOption::VALUE_REQUIRED, 'Old DB user', 'root')
            ->addOption('old-password', null, InputOption::VALUE_REQUIRED, 'Old DB password', '')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Old table prefix', 'forum_')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate import without writing data')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('FluxBB 1.5 → Next Migration');

        // Build old database connection
        $oldDbUrl = (string) $input->getOption('old-db-url');
        if ($oldDbUrl !== '') {
            /** @psalm-suppress InvalidArgument — Doctrine DBAL 4 accepts 'url' key, but Psalm type is strict */
            $oldConnection = \Doctrine\DBAL\DriverManager::getConnection(['url' => $oldDbUrl]);
        } else {
            $oldDriver = (string) $input->getOption('old-driver');
            $oldHost = (string) $input->getOption('old-host');
            $oldPort = (int) $input->getOption('old-port');
            $oldDbname = (string) $input->getOption('old-dbname');
            $oldUser = (string) $input->getOption('old-user');
            $oldPassword = (string) $input->getOption('old-password');

            $oldConnection = \Doctrine\DBAL\DriverManager::getConnection([
                'driver' => $oldDriver,
                'host' => $oldHost,
                'port' => $oldPort,
                'dbname' => $oldDbname,
                'user' => $oldUser,
                'password' => $oldPassword,
            ]);
        }

        $prefix = (string) $input->getOption('prefix');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->section('Connecting to old database...');
        try {
            // Verify connection works by executing a simple query
            $oldConnection->executeQuery('SELECT 1');
            $io->success('Connected to old database.');
        } catch (\Throwable $e) {
            $io->error('Cannot connect to old database: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->warning('Dry-run mode: no data will be written.');
        }

        $io->section('Importing data...');

        // Run the importer
        $importer = new FluxBB15Importer(
            source: $oldConnection,
            target: $this->targetConnection,
            tablePrefix: $prefix,
        );

        $stats = $importer->import();

        // Display results
        $io->section('Import results');

        $rows = [];
        foreach ($stats as $table => $count) {
            $rows[] = [$table, (string) $count];
        }

        $io->table(['Table', 'Rows imported'], $rows);

        $total = array_sum($stats);
        $io->success(sprintf('Import complete! %d total rows migrated.', $total));

        return Command::SUCCESS;
    }
}