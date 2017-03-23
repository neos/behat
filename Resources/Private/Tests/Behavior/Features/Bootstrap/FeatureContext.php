<?php

use Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;

require_once(__DIR__ . '/../../../../../Neos.Behat/Tests/Behat/FlowContext.php');

/**
 * Features context
 */
class FeatureContext extends MinkContext {
	/**
	 * @var \Neos\Flow\ObjectManagement\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * Initializes the context
	 *
	 * @param array $parameters Context parameters (configured through behat.yml)
	 */
	public function __construct(array $parameters) {
		$this->useContext('flow', new \Neos\Behat\Tests\Behat\FlowContext($parameters));
		$this->objectManager = $this->getSubcontext('flow')->getObjectManager();
	}

	/**
	* @Then /^I should see some output from behat$/
	*/
	public function iShouldSeeSomeOutputFromBehat() {
		return TRUE;
	}
}
