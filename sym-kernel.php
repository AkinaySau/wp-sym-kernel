<?php
/**
 * Plugin Name: Sym Kernel
 * Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: A brief description of the Plugin.
 * Version: 1.0
 * Author: sau
 * Author URI: http://URI_Of_The_Plugin_Author
 * License: A "Slug" license name e.g. GPL2
 */

use Symfony\Component\Filesystem\Filesystem;

register_activation_hook(__FILE__, function () {
    require_once __DIR__.'/vendor/autoload.php';
    require_once __DIR__.'/.defines.php';

    $fs = new Filesystem();
    ###Create symlink loader###
    if ($fs->exists(SAU_EXTEND_PATH_LOADER)) {
        $fs->remove(SAU_EXTEND_PATH_LOADER);
    }
    $fs->symlink(__DIR__.'/.loader.php', SAU_EXTEND_PATH_LOADER);
    ###Create ENV file###
    if (!$fs->exists(ABSPATH.'/.env')) {
        $fs->copy(__DIR__.'/.env', ABSPATH.'/.env');
    }
});
register_deactivation_hook(__FILE__, function () {
    require_once __DIR__.'/vendor/autoload.php';
    $fs = new Filesystem();
    if ($fs->exists(SAU_EXTEND_PATH_LOADER)) {
        $fs->remove(SAU_EXTEND_PATH_LOADER);
    }
});
