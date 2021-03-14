<?php
namespace Neos\Behat\Tests\Behat;

/*
 * This file is part of the Neos.Behat package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Behat\Tests\Functional\Aop\ConsoleLoggingCaptureAspect;
use Neos\Behat\Tests\Functional\Fixture\FixtureFactory;
use Neos\Flow\Cli\CommandRequestHandler;
use Neos\Flow\Cli\RequestBuilder;
use Neos\Flow\Cli\Response;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Http\Uri;
use Neos\Flow\Mvc\Dispatcher;
use Neos\Flow\Mvc\Routing\Router;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Persistence\Doctrine\Service;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Flow\Security\Policy\PolicyService;
use PHPUnit\Framework\Assert;

trait FlowContextTrait
{

    /**
     * @var Bootstrap
     */
    static protected $bootstrap;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Doctrine\DBAL\Schema\Schema
     */
    protected static $databaseSchema;

    /**
     * @var string
     */
    protected $lastCommandOutput;

    /**
     * Create a flow bootstrap instance
     */
    protected function initializeFlow(): Bootstrap
    {
        require_once(__DIR__ . '/../../../../Framework/Neos.Flow/Classes/Core/Bootstrap.php');
        if (!defined('FLOW_PATH_ROOT')) {
            define('FLOW_PATH_ROOT', realpath(__DIR__ . '/../../../../..') . '/');
        }
        // The new classloader needs warnings converted to exceptions
        if (!defined('BEHAT_ERROR_REPORTING')) {
            define('BEHAT_ERROR_REPORTING', E_ALL);
        }
        $bootstrap = new Bootstrap('Testing/Behat');
        Scripts::initializeClassLoader($bootstrap);
        Scripts::initializeSignalSlot($bootstrap);
        Scripts::initializePackageManagement($bootstrap);
        // FIXME: We NEED to define a request due to return type declarations, and with the
        // current state of the behat test (setup) we cannot use a Http\RequestHandler because
        // some code would then try to access the httpRequest and Response which is not available,
        // so we need to think if we "mock" the whole component chain and a Http\RequestHandler or
        // live with having a CommandRequestHandler here. (A specialisted TestHandler for this case
        // would probably be a good idea.
        $bootstrap->setActiveRequestHandler(new CommandRequestHandler($bootstrap));
        $bootstrap->buildRuntimeSequence()->invoke($bootstrap);

        return $bootstrap;
    }

    /**
     * @AfterSuite
     */
    public static function shutdownFlow(): void
    {
        if (self::$bootstrap !== null) {
            self::$bootstrap->shutdown('Runtime');
        }
    }

    /**
     * @When /^(?:|I )run the command "([^"]*)"$/
     */
    public function iRunTheCommand($command): void
    {
        $captureAspect = $this->objectManager->get(ConsoleLoggingCaptureAspect::class);
        $captureAspect->reset();

        $captureAspect->disableOutput();

        try {
            $request = $this->objectManager->get(RequestBuilder::class)->build($command);
            $response = new Response();

            $dispatcher = $this->objectManager->get(Dispatcher::class);
            $dispatcher->dispatch($request, $response);

            $this->lastCommandOutput = $captureAspect->getCapturedOutput();

            $this->persistAll();
        } finally {
            $captureAspect->enableOutput();
        }
    }

    /**
     * @Then /^(?:|I )should see the command output "([^"]*)"$/
     */
    public function iShouldSeeTheCommandOutput($line): void
    {
        Assert::assertContains($line, explode(PHP_EOL, $this->lastCommandOutput));
    }

    /**
     * @Then /^(P|p)rint last command output$/
     */
    public function printLastCommandOutput(): void
    {
        $this->printDebug($this->lastCommandOutput);
    }

    /**
     * @Then /^(?:|I )should see "([^"]*)" in the command output$/
     */
    public function iShouldSeeSomethingInTheCommandOutput($contents): void
    {
        Assert::assertContains($contents, $this->lastCommandOutput);
    }

    /**
     * @BeforeScenario @fixtures
     */
    public function resetTestFixtures($event): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->objectManager->get(EntityManagerInterface::class);
        $entityManager->clear();

        if (self::$databaseSchema !== null) {
            $this->truncateTables($entityManager);
        } else {
            try {
                /** @var Service $doctrineService */
                $doctrineService = $this->objectManager->get(Service::class);
                $doctrineService->executeMigrations();
                $needsTruncate = true;
            } catch (DBALException $exception) {
                // Do an initial teardown to drop the schema cleanly
                $this->objectManager->get(PersistenceManagerInterface::class)->tearDown();

                /** @var Service $doctrineService */
                $doctrineService = $this->objectManager->get(Service::class);
                $doctrineService->executeMigrations();
                $needsTruncate = false;
            } catch (\PDOException $exception) {
                if ($exception->getMessage() !== 'There is no active transaction') {
                    throw $exception;
                }
                $needsTruncate = true;
            }

            $schema = $entityManager->getConnection()->getSchemaManager()->createSchema();
            self::$databaseSchema = $schema;

            if ($needsTruncate) {
                $this->truncateTables($entityManager);
            }

            // FIXME Check if this is needed at all!
            $proxyFactory = $entityManager->getProxyFactory();
            $proxyFactory->generateProxyClasses($entityManager->getMetadataFactory()->getAllMetadata());
        }

        $this->resetFactories();
    }

    /**
     * Truncate all known tables
     *
     * @param EntityManagerInterface $entityManager
     * @return void
     */
    public function truncateTables(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();

        $tables = array_filter(self::$databaseSchema->getTables(), function ($table) {
            return $table->getName() !== 'flow_doctrine_migrationstatus';
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

    /**
     * Reset factory instances
     *
     * Must be called after all persistAll calls and before scenarios to have a clean state.
     *
     * @return void
     */
    protected function resetFactories(): void
    {
        /** @var $reflectionService ReflectionService */
        $reflectionService = $this->objectManager->get(ReflectionService::class);
        $fixtureFactoryClassNames = $reflectionService->getAllSubClassNamesForClass(FixtureFactory::class);
        foreach ($fixtureFactoryClassNames as $fixtureFactoryClassName) {
            if (!$reflectionService->isClassAbstract($fixtureFactoryClassName)) {
                $factory = $this->objectManager->get($fixtureFactoryClassName);
                $factory->reset();
            }
        }

        $this->resetRolesAndPolicyService();
    }

    /**
     * Reset policy service and role repository
     *
     * This is needed to remove cached role entities after resetting the database.
     *
     * @deprecated
     */
    protected function resetRolesAndPolicyService(): void
    {
        $this->resetPolicyService();
    }

    /**
     * Reset policy service
     *
     * This is needed to remove cached role entities after resetting the database.
     *
     * @return void
     */
    protected function resetPolicyService(): void
    {
        $this->objectManager->get(PolicyService::class)->reset();
    }

    /**
     * Persist any changes
     */
    public function persistAll()
    {
        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
        $this->objectManager->get(PersistenceManagerInterface::class)->clearState();

        $this->resetFactories();
    }

    /**
     * @return Router
     */
    protected function getRouter()
    {
        if ($this->router === null) {
            $this->router = $this->objectManager->get(Router::class);

            $configurationManager = $this->objectManager->get(ConfigurationManager::class);
            $routesConfiguration = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_ROUTES);
            $this->router->setRoutesConfiguration($routesConfiguration);
        }
        return $this->router;
    }

    /**
     * Resolve a path by route name or a relative path (as a fallback)
     *
     * @param string $pageName
     * @return string
     * @deprecated Use resolvePageUri
     */
    public function resolvePath($pageName): string
    {
        return $this->resolvePageUri($pageName);
    }

    /**
     * Resolves a URI for the given page name
     *
     * If a Flow route with a name equal to $pageName exists it will be resolved.
     * An absolute path will be used as is for compatibility with the default MinkContext.
     *
     * @param string $pageName
     * @param array $arguments
     * @return string
     * @throws \InvalidArgumentException
     */
    public function resolvePageUri($pageName, array $arguments = null): string
    {
        $uri = null;
        if (strpos($pageName, '/') === 0) {
            $uri = $pageName;
            return $uri;
        } else {
            $router = $this->getRouter();

            /** @var \Neos\Flow\Mvc\Routing\Route $route */
            foreach ($router->getRoutes() as $route) {
                if (preg_match('/::\s*' . preg_quote($pageName, '/') . '$/', $route->getName())) {
                    $routeValues = $route->getDefaults();
                    if (is_array($arguments)) {
                        $routeValues = array_merge($routeValues, $arguments);
                    }
                    if ($route->resolves($routeValues)) {
                        $resolvedUriConstraints = $route->getResolvedUriConstraints();
                        $uri = $resolvedUriConstraints->applyTo(new Uri('http://localhost'), false);
                        break;
                    }
                }
            }
            if ($uri === null) {
                throw new \InvalidArgumentException('Could not resolve a route for name "' . $pageName . '"');
            }
            if (strpos($uri, 'http') !== 0 && strpos($uri, '/') !== 0) {
                $uri = '/' . $uri;
            }
            return $uri;
        }
    }

    /**
     * @return ObjectManagerInterface
     */
    public function getObjectManager(): ObjectManagerInterface
    {
        return $this->objectManager;
    }

    /**
     * @return string
     */
    public function getLastCommandOutput(): string
    {
        return $this->lastCommandOutput;
    }

    /**
     * Prints beautified debug string.
     *
     * @param string $string debug string
     */
    public function printDebug($string): void
    {
        echo "\n\033[36m|  " . strtr($string, ["\n" => "\n|  "]) . "\033[0m\n\n";
    }
}
