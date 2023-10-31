<?php

use Behat\Behat\Context\Context;
use Neos\Behat\FlowBootstrapTrait;
use Neos\Flow\Configuration\ConfigurationManager;

class FeatureContext implements Context
{
    use FlowBootstrapTrait;

    public function __construct()
    {
        self::bootstrapFlow();
    }

    /**
     * @Then I should see some output from behat
     */
    public function iShouldSeeSomeOutputFromBehat(): void
    {
        $configurationManager = $this->getObject(ConfigurationManager::class);
        echo json_encode($configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow.core'));
        die();
    }
}
