<?php
/**
 * Created by PhpStorm.
 * User: AkinaySau(akinaysau@gmail.com)
 * Date: 14.12.18
 * Time: 12:39
 *
 * @package sym-kernel
 */

namespace Sau\WP\Plugin\SymKernel;


use Sau\WP\Plugin\SymKernel\Filter\BundleFilter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

abstract class AbstractBundle extends Bundle
{

    /**
     * @var string
     */
    protected $router_prefix = '/';

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $this->loadConfigs();
    }

    public function getConfDirPath()
    {
        return $this->getPath().'/Resources/config/';
    }

    protected function loadConfigs()
    {
        $confDir = $this->getConfDirPath();
        BundleFilter::addRoutes($confDir.'{routing}'.Kernel::CONFIG_EXTS, $this->getRouterPrefix());
        BundleFilter::addService($confDir.'{services}'.Kernel::CONFIG_EXTS);
    }

    /**
     * @return string
     */
    public function getRouterPrefix(): string
    {
        return $this->router_prefix;
    }
}
