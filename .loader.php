<?php
/**
 * Created by PhpStorm.
 * User: sau
 * Date: 12.12.2018
 * Time: 22:06
 */

/**
 * Need init as mu plugin, add access for all actions
 */

use Sau\WP\Plugin\SymKernel\Kernel;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/.defines.php';

###Create env###
$path_to_env = ABSPATH.'.env';
if ( ! file_exists($path_to_env)) {
    $path_to_env = __DIR__.'/.env';
}
if ( ! class_exists(Dotenv::class)) {
    throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}
if ( ! file_exists($path_to_env)) {
    throw new \RuntimeException('Env file not found');
}
(new Dotenv())->load($path_to_env);
###end load env###


$env   = $_SERVER[ 'APP_ENV' ] ?? 'prod';
$debug = (bool)($_SERVER[ 'APP_DEBUG' ] ?? ('prod' !== $env));


###load debug###
if ($debug) {
    umask(0000);
    Debug::enable();
    if (class_exists(ErrorHandler::class)) {
        ErrorHandler::register();
    }
    if (class_exists(ExceptionHandler::class)) {
        ExceptionHandler::register();
    }
    //    if (class_exists(ExceptionHandler::class)) {
    //        DebugClassLoader::enable();
    //    }
}
###end debug###

$request = Request::createFromGlobals();
$kernel  = new Kernel($env, $debug, $request);

//send kernel in init action
add_action('init', function () use ($kernel, $request) {
    $kernel->boot();
    if ( ! defined('WP_ADMIN') || WP_ADMIN === false) {
        try {
            $response = $kernel->handle($request, HttpKernelInterface::MASTER_REQUEST, true);
            if ($response->isOk()) {
                $response->send();
                $kernel->terminate($request, $response);
            }
        } catch (RouteNotFoundException $exception) {
        }

    }
});
