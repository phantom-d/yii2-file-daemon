<?php

namespace phantomd\filedaemon;

use Composer\Installer\LibraryInstaller;
use Composer\Util\Filesystem;
use Composer\Installer\PackageEvent;
use Composer\EventDispatcher\Event;
use Composer\Package\Package;

/**
 * Class Installer. Create base daemon configuration after install
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Installer extends LibraryInstaller
{

    /**
     * Create base configuration
     *
     * @param PackageEvent $event Package event
     */
    public static function createConfigure(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        $packageDir = $package->getName();

        if ('phantom-d/yii2-file-daemon' === $packageDir) {
            $fs         = new Filesystem();
            $composer   = $event->getComposer();
            $vendorDir  = $composer->getConfig()->get('vendor-dir');
            $rootDir    = dirname($vendorDir);

            $prefix = '';

            if (is_dir($rootDir . DIRECTORY_SEPARATOR . 'common/config')) {
                $prefix = 'common' . DIRECTORY_SEPARATOR;
            }

            $configPath = $rootDir . DIRECTORY_SEPARATOR . $prefix . 'config/daemons';
            $fs->ensureDirectoryExists($configPath);

            $path = $vendorDir
                . DIRECTORY_SEPARATOR . $packageDir
                . DIRECTORY_SEPARATOR . 'config/daemons.php';

            if (file_exists($path)) {
                copy($path, $configPath . DIRECTORY_SEPARATOR . 'daemons.php');
            }
        }
    }

}
