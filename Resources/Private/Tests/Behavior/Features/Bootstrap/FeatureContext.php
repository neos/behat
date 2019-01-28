<?php

use Behat\Behat\Context\Context;
use Neos\Behat\Tests\Behat\FlowContextTrait;

require_once(__DIR__ . '/../../../../../../Application/Neos.Behat/Tests/Behat/FlowContextTrait.php');

/**
 * Features context
 */
class FeatureContext implements Context
{
    use FlowContextTrait;

    /**
     * @var \Neos\Flow\ObjectManagement\ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct()
    {
        if (self::$bootstrap === null) {
            self::$bootstrap = $this->initializeFlow();
        }
        $this->objectManager = self::$bootstrap->getObjectManager();
    }

    /**
     * @Then /^I should see some output from behat$/
     * @return bool
     */
    public function iShouldSeeSomeOutputFromBehat(): bool
    {
        $this->printDebug('Can you see me?');
        return true;
    }
}
