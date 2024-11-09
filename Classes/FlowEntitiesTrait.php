<?php

declare(strict_types=1);

namespace Neos\Behat;

use Behat\Hook\BeforeScenario;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Persistence\Doctrine\Service as FlowDoctrineService;
use Neos\Flow\Persistence\PersistenceManagerInterface;

/**
 * Tag your test with [at]flowEntities to enable support for flow entities
 *
 * @api
 */
trait FlowEntitiesTrait
{
    /** @internal */
    private static ?Schema $databaseSchema = null;

    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    abstract protected function getObject(string $className): object;

    /** @internal */
    #[BeforeScenario('@flowEntities')]
    final public function truncateAndSetupFlowEntities(): void
    {
        $entityManager = $this->getObject(EntityManagerInterface::class);
        $entityManager->clear();
        // the same connection as the singleton: $this->getObject(Connection::class);
        $connection = $entityManager->getConnection();

        if (self::$databaseSchema !== null) {
            $this->truncateTables($connection);
        } else {
            try {
                $doctrineService = $this->getObject(FlowDoctrineService::class);

                $doctrineService->executeMigrations();
                // Hotfix reconnecting resets the currently active transactions
                $connection->close();
                $needsTruncate = true;
            } catch (DBALException $exception) {
                // Do an initial teardown to drop the schema cleanly
                $this->getObject(PersistenceManagerInterface::class)->tearDown();

                $doctrineService = $this->getObject(FlowDoctrineService::class);
                $doctrineService->executeMigrations();
                // Hotfix reconnecting resets the currently active transactions
                $connection->close();
                $needsTruncate = false;
            } catch (\PDOException $exception) {
                if ($exception->getMessage() !== 'There is no active transaction') {
                    throw $exception;
                }
                $needsTruncate = true;
            }

            $schema = $connection->getSchemaManager()->createSchema();
            self::$databaseSchema = $schema;

            if ($needsTruncate) {
                $this->truncateTables($connection);
            }
        }

       // \var_dump([
       //     'getTransactionNestingLevel' => $connection->getTransactionNestingLevel(),
       //     'isRollbackOnly' => $connection->isTransactionActive() ? $connection->isRollbackOnly() : null,
       //     'isTransactionActive' => $connection->isTransactionActive(),
       // ]);
       // die();

    }

    /** @internal */
    private function truncateTables(Connection $connection): void
    {
        /**
         * We respect flows option "ignoredTables" to preserve certain tables while resetting the database.
         * In our case we interpret everything in "ignoredTables" as not managed by doctrine.
         * And this trait should only clear tables for @flowEntities.
         * An important use case is keeping the Neos ESCR tables `cr_*.` alive for speeding up tests.
         *
         * Docs for original idea of "ignoredTables": {@link https://flowframework.readthedocs.io/en/9.0/TheDefinitiveGuide/PartIII/Persistence.html#ignoring-tables}
         *
         * Implementation copied from {@link https://github.com/neos/flow-development-collection/blob/ed6a26603f682966816c71840524c7da6ed919a5/Neos.Flow/Classes/Command/DoctrineCommandController.php#L468-L474}
         */
        $ignoredTables = $this->getObject(ConfigurationManager::class)
            ->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow.persistence.doctrine.migrations.ignoredTables') ?? [];
        $filterExpression = null;
        $ignoredTables = array_keys(array_filter($ignoredTables));
        if ($ignoredTables !== []) {
            $filterExpression = sprintf('/^(?!%s$).*$/xs', implode('$|', $ignoredTables));
        }

        $tables = array_filter(self::$databaseSchema->getTables(), function ($table) use ($filterExpression) {
            if ($table->getName() === FlowDoctrineService::DOCTRINE_MIGRATIONSTABLENAME) {
                return false;
            }
            if ($filterExpression === null) {
                return true;
            }
            return preg_match($filterExpression, $table->getName()) === 1;
        });

        switch ($connection->getDatabasePlatform()->getName()) {
            case 'mysql':
                $sql = 'SET FOREIGN_KEY_CHECKS=0;';
                foreach ($tables as $table) {
                    $sql .= 'TRUNCATE `' . $table->getName() . '`;';
                }
                $sql .= 'SET FOREIGN_KEY_CHECKS=1;';
                $connection->executeQuery($sql);
                break;
            case 'sqlite':
                $sql = 'PRAGMA foreign_keys = OFF;';
                foreach ($tables as $table) {
                    $sql .= 'DELETE FROM `' . $table->getName() . '`;';
                }
                $sql .= 'PRAGMA foreign_keys = ON;';
                $connection->executeQuery($sql);
                break;
            case 'postgresql':
            default:
                foreach ($tables as $table) {
                    $sql = 'TRUNCATE ' . $table->getName() . ' CASCADE;';
                    $connection->executeQuery($sql);
                }
                break;
        }
    }
}
