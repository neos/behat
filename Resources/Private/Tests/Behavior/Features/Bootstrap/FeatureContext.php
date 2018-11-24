<?php

use Behat\MinkExtension\Context\MinkContext;
use Neos\Behat\Tests\Behat\FlowContextTrait;

require_once __DIR__ . '/../../../../../Neos.Behat/Tests/Behat/FlowContextTrait.php';

/**
 * Features context
 */
class FeatureContext extends MinkContext
{
    use FlowContextTrait;

    /**
     * @var \Neos\Flow\ObjectManagement\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Initializes the context
     *
     * @param array $parameters Context parameters (configured through behat.yml)
     */
    public function __construct(array $parameters)
    {
        if (self::$bootstrap === null) {
            self::$bootstrap = $this->initializeFlow();
        }
        $this->objectManager = self::$bootstrap->getObjectManager();
    }

    /**
     * @Then /^I should see some output from behat$/
     */
    public function iShouldSeeSomeOutputFromBehat()
    {
        $this->printDebug('Can you see me?');
        return true;
    }
}
