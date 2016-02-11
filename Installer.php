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
        $filesystem = new Filesystem();
        $composer = $event->getComposer();
        
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $package = $event->getOperation()->getPackage();

        echo PHP_EOL,
        '$package(' . __LINE__ . '): ', print_r($package, true), PHP_EOL,
        PHP_EOL;
    
    }

}
