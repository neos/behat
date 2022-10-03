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

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Package\Exception\UnknownPackageException;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Exception\FilesException;
use Neos\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class BehatCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * This command will help you to install Behat
     *
     * It will check for all necessary things to run Behat tests.
     *
     * @param bool $skipSeleniumDownload if true, the selenium binary download is skipped
     * @return void
     * @throws FilesException
     */
    public function setupCommand(bool $skipSeleniumDownload = false): void
    {
        $behatBuildPath = FLOW_PATH_ROOT . 'Build/Behat/';
        if (is_dir($behatBuildPath)) {
            Files::emptyDirectoryRecursively($behatBuildPath);
        }
        $this->outputLine('Copying Behat setup to %s', [$behatBuildPath]);
        Files::copyDirectoryRecursively('resource://Neos.Behat/Private/Build/Behat', $behatBuildPath);

        $behatBinaryPath = FLOW_PATH_ROOT . 'bin/behat';
        if (is_file($behatBinaryPath)) {
            $this->outputLine('Removing existing Behat binary %s', [$behatBinaryPath]);
            unlink($behatBinaryPath);
        }
        $this->outputLine('Installing Behat in %s', [$behatBuildPath]);
        exec('cd "' . $behatBuildPath . '" && composer install');
        $this->outputLine();
        $this->outputLine('Installed Behat to %s, binary to %s', [$behatBuildPath, $behatBinaryPath]);

        if ($skipSeleniumDownload) {
            $this->outputLine('Skipped download of Selenium via argument --skip-selenium-download=true');
        } else {
            $seleniumBinaryPath = FLOW_PATH_ROOT . 'bin/selenium-server.jar';
            if (!is_file($seleniumBinaryPath)) {
                $seleniumVersion = 'selenium-server-standalone-2.53.1.jar';
                $seleniumUrl = 'https://selenium-release.storage.googleapis.com/2.53/' . $seleniumVersion;
                $this->outputLine('Downloading Selenium %s to bin/selenium-server.jar...', [$seleniumVersion]);
                if (copy($seleniumUrl, FLOW_PATH_ROOT . 'bin/selenium-server.jar') !== true) {
                    throw new \RuntimeException('Could not download selenium from ' . $seleniumUrl . '.');
                }
                $this->outputLine('Downloaded Selenium to bin/selenium-server.jar');
                $this->outputLine('You can execute it through: "java -jar selenium-server.jar"');
            } else {
                $this->outputLine('Skipped downloaded of Selenium, to update or reinstall delete bin/selenium-server.jar and run setup again');
            }
        }
    }

    /**
     * This command will help you to kickstart Behat testing for a package
     *
     * It will add a folder Tests/Behavior in your package with a default Behat setup.
     *
     * @param string $packageName The package key
     * @param string $host The base URL for the Flow application (e.g. http://example.local/)
     * @return void
     * @throws FilesException
     * @throws UnknownPackageException
     */
    public function kickstartCommand(string $packageName, string $host): void
    {
        $this->setupCommand();

        if ($this->packageManager->isPackageAvailable($packageName)) {
            $package = $this->packageManager->getPackage($packageName);

            $behaviorTestsPath = $package->getPackagePath() . 'Tests/Behavior';
            if (!is_dir($behaviorTestsPath)) {
                Files::copyDirectoryRecursively('resource://Neos.Behat/Private/Tests/Behavior', $behaviorTestsPath);
            }

            $behatConfiguration = file_get_contents($behaviorTestsPath . '/behat.yml.dist');
            $behatConfiguration = str_replace('base_url: http://localhost/', 'base_url: ' . $host, $behatConfiguration);
            file_put_contents($behaviorTestsPath . '/behat.yml', $behatConfiguration);
        }
        $this->outputLine('Behat is installed and can be used by running: "bin/behat -c Packages/Application/%s/Tests/Behavior/behat.yml"', [$packageName]);
    }
}
