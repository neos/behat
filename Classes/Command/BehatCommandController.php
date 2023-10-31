<?php
namespace Neos\Behat\Command;

/*
 * This file is part of the Neos.Behat package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Cli\CommandController;

/**
 * @deprecated only in place to yield a helpful error when running `behat:setup`
 */
class BehatCommandController extends CommandController
{
    /**
     * This command was helping you to install Behat
     *
     * This command added Behat to the "Build/Behat" folder, installed a binary to
     * "bin/behat" and download a current version of the selenium server to "bin/selenium-server.jar".
     *
     * @deprecated only in place to yield a helpful error when running `behat:setup`
     */
    public function setupCommand(): void
    {
        $this->outputLine('<error>flow behat:setup has been removed. You don\'t need to install behat at another location. Please use behat natively in your main composer installation. See the readme for more details.</error>');
        $this->quit(1);
    }
}
