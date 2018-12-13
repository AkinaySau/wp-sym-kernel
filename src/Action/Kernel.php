<?php
/**
 * Created by PhpStorm.
 * User: sau
 * Date: 13.12.2018
 * Time: 21:42
 */

namespace Sau\WP\Plugin\SymKernel\Action;


use Sau\WP\Plugin\SymKernel\Kernel;

/**
 * Hooks run in action init then @see Kernel object call method "boot"
 * @package Sau\WP\Plugin\SymKernel\Action
 */
class KernelActions extends AbstractAction
{
    public static function beforeBoot($callable)
    {
        static::action('sym_kernel_boot_before', $callable);
    }

    public static function afterBoot($callable)
    {
        static::action('sym_kernel_boot_after', $callable);
    }
}
