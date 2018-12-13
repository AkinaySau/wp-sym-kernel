<?php
/**
 * Created by PhpStorm.
 * User: sau
 * Date: 13.12.2018
 * Time: 21:39
 */

namespace Sau\WP\Plugin\SymKernel\Action;


abstract class AbstractAction
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
     * @param callable $hook          Hook name
     * @param callable $action        Callback
     * @param int      $priority      Priority
     * @param int      $accepted_args Count arguments
     */
    public final static function action($hook, $action, $priority = null, $accepted_args = null)
    {
        $priority      = $priority ?? self::PRIORITY;
        $accepted_args = $accepted_args ?? self::ACCEPTED_ARGS;
        add_action($hook, $action, (int)$priority, (int)$accepted_args);
    }
}
