<?php
/**
 * Created by PhpStorm.
 * User: sau
 * Date: 13.12.2018
 * Time: 22:37
 */

namespace Sau\WP\Plugin\SymKernel\Filter;


class BundleFilter extends AbstractFilter
{
    /**
     * Simple registration new bundle
     *
     * @param string $class
     * @param array  $env
     */
    public static function addBundle(string $class, array $env)
    {
        static::filter('sym_kernel_add_bundles', function ($bundles) use ($class, $env) {
            return array_merge($bundles, [$class => $env]);
        });
    }

    /**
     * Simple registration new routers
     *
     * @param string $path
     * @param string $prefix
     */
    public static function addRoutes(string $path, string $prefix = '/')
    {
        static::filter('sym_kernel_add_routes', function ($files) use ($path, $prefix) {
            $files[] = ['path' => $path, 'prefix' => $prefix];

            return $files;
        });
    }

    /**
     * Simple registration new routers
     *
     * @param string $path
     */
    public static function addService(string $path)
    {
        static::filter('sym_kernel_add_service', function ($files) use ($path) {
            $files[] = $path;

            return $files;
        });
    }
}
