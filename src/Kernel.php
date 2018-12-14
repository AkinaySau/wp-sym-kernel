<?php
/**
 * Created by PhpStorm.
 * User: sau
 * Date: 13.12.2018
 * Time: 20:32
 */

namespace Sau\WP\Plugin\SymKernel;

use Sau\WP\Plugin\SymKernel\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    const CONFIG_EXTS = '.{php,xml,yaml,yml}';
    /**
     * @var array base bundles
     */
    protected $base_bundles = [
        \Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
        \Symfony\Bundle\TwigBundle\TwigBundle::class           => ['all' => true],
    ];
    /**
     * @var Request
     */
    protected $request;

    public function __construct(string $environment, bool $debug, Request $request)
    {
        parent::__construct($environment, $debug);
        $this->request = $request;
    }

    public function getCacheDir()
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getLogDir()
    {
        return $this->getProjectDir().'/var/log';
    }

    public function registerBundles()
    {
        $bundles  = apply_filters('sym_kernel_add_bundles', []); #filter for add new bundle
        $contents = array_merge($this->base_bundles, $bundles);
        foreach ($contents as $class => $envs) {
            if (isset($envs[ 'all' ]) || isset($envs[ $this->environment ])) {
                yield new $class();
            }
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param LoaderInterface  $loader
     *
     * @throws \Exception
     */
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader)
    {
        $container->addResource(new FileResource($this->getProjectDir().'/config/bundles.php'));
        // Feel free to remove the "container.autowiring.strict_mode" parameter
        // if you are using symfony/dependency-injection 4.0+ as it's the default behavior
        $container->setParameter('container.autowiring.strict_mode', true);
        $container->setParameter('container.dumper.inline_class_loader', true);
        $confDir = $this->getProjectDir().'/config';

        $loader->load($confDir.'/{packages}/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{packages}/'.$this->environment.'/**/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}_'.$this->environment.self::CONFIG_EXTS, 'glob');

        $other = apply_filters('sym_kernel_add_service', []);
        foreach ($other as $item) {
            $loader->load($item, 'glob');
        }
    }

    /**
     * @param RouteCollectionBuilder $routes
     *
     * @throws \Symfony\Component\Config\Exception\LoaderLoadException
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $confDir = $this->getProjectDir().'/config';

        $routes->import($confDir.'/{routes}/*'.self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir.'/{routes}/'.$this->environment.'/**/*'.self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir.'/{routes}'.self::CONFIG_EXTS, '/', 'glob');

        $other = apply_filters('sym_kernel_add_routes', []);
        foreach ($other as $item) {
            $routes->import($item[ 'path' ], $item[ 'prefix' ], 'glob');
        }
    }

    /**
     * @param Request $request
     * @param int     $type
     * @param bool    $catch
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        if ( ! $this->booted) {
            $this->boot();
        }

        if ( ! $this->request instanceof Request) {
            $this->request = $request;
        }

        return $this->getHttpKernel()
                    ->handle($this->request, $type, $catch);
    }


}
