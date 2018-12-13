<?php
/**
 * Created by PhpStorm.
 * User: sau
 * Date: 13.12.2018
 * Time: 22:43
 */

namespace Sau\WP\Plugin\SymKernel\Filter;


abstract class AbstractFilter
{
    /**
     * Priority (default)
     *
     * @var int
     */
    const PRIORITY = 10;
    /**
     * Count params (default 1)
     *
     * @var int
     */
    const ACCEPTED_ARGS = 1;

    /**
     * Hook
     *
     * @param callable $tag           Hook name
     * @param callable $callback      Callback
     * @param int      $priority      Priority
     * @param int      $accepted_args Count arguments
     *
     * @return true|void
     */
    public final static function filter($tag, $callback, $priority = null, $accepted_args = null)
    {
        $priority      = $priority ?? self::PRIORITY;
        $accepted_args = $accepted_args ?? self::ACCEPTED_ARGS;
        add_filter($tag, $callback, (int)$priority, (int)$accepted_args);
    }
}
