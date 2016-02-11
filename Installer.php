<?php

namespace phantomd\filedaemon;

use Composer\Installer\LibraryInstaller;
use Composer\Util\Filesystem;

/**
 * Class Installer. Fabrica for processing.
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Installer extends LibraryInstaller
{

    public static function createConfigure($event)
    {
        $fs       = new Filesystem();
        $composer = $event->getComposer();

        $vendorDir  = $composer->getConfig()->get('vendor-dir');
        $packageDir = $composer->getPackage()->getName();
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
