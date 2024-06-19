<?php

declare(strict_types=1);

namespace Neos\Behat;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Exception\SubProcessException;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Testing\RequestHandler\RuntimeSequenceHttpRequestHandler;
use Psr\Http\Message\ServerRequestFactoryInterface;

/**
 * Boot flow in a behat feature context
 *
 * ```
 * class FeatureContext implements Context
 * {
 *     use FlowBootstrapTrait;
 *
 *     public function __construct()
 *     {
 *         self::bootstrapFlow();
 *     }
 * }
 * ```
 *
 * @api
 */
trait FlowBootstrapTrait
{
    /**
     * @internal please use {@see self::getObject()} to get objects
     */
    private static ?Bootstrap $bootstrap = null;

    /**
     * Create a flow bootstrap instance
     * @api
     */
    final protected static function bootstrapFlow(): Bootstrap
    {
        if (self::$bootstrap !== null) {
            // already initialized
            return self::$bootstrap;
        }
        require_once dirname(__DIR__, 4) . '/Packages/Framework/Neos.Flow/Classes/Core/Bootstrap.php';
        if (!defined('FLOW_PATH_ROOT')) {
            define('FLOW_PATH_ROOT', dirname(__DIR__, 4) . '/');
        }
        // The new classloader needs warnings converted to exceptions
        if (!defined('BEHAT_ERROR_REPORTING')) {
            define('BEHAT_ERROR_REPORTING', E_ALL);
        }

        // Boot flow with an active request handler
        $flowBootstrap = new Bootstrap('Testing/Behat');
        $requestHandler = new RuntimeSequenceHttpRequestHandler($flowBootstrap);

        $flowBootstrap->registerRequestHandler($requestHandler);
        $flowBootstrap->setPreselectedRequestHandlerClassName($requestHandler::class);

        $flowBootstrap->run();

        $serverRequestFactory = $flowBootstrap->getObjectManager()->get(ServerRequestFactoryInterface::class);

        $request = $serverRequestFactory->createServerRequest('GET', 'http://localhost');

        $requestHandler->setHttpRequest($request);

        return self::$bootstrap = $flowBootstrap;
    }

    /**
     * @AfterSuite
     * @internal
     */
    final public static function shutdownFlow(): void
    {
        if (self::$bootstrap !== null) {
            self::$bootstrap->shutdown('Runtime');
        }
    }

    /**
     * When needing dependencies, your trait should use this method (and declare it as abstract)
     *
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     *
     * @api
     */
    final protected function getObject(string $className): object
    {
        if (self::$bootstrap === null) {
            throw new \RuntimeException('Could not get object, because flow is not initialized.', 1698765963);
        }
        return self::$bootstrap->getObjectManager()->get($className);
    }

    /**
     * Executes the specified $command in the context of the application to test
     * @api
     */
    final protected function executeCommand(string $command, array $commandArguments): void
    {
        $configurationManager = $this->getObject(ConfigurationManager::class);
        $flowSettings = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow');
        try {
            Scripts::executeCommand($command, $flowSettings, false, $commandArguments);
        } catch (SubProcessException $e) {
            throw new \RuntimeException(sprintf('An exception was thrown while executing command "%s"; %s', $command, $e->getMessage()), 1549290029, $e);
        }
    }
}
