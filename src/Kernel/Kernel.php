<?php
/**
 * Created by PhpStorm.
 * User: sau
 * Date: 12.12.2018
 * Time: 22:24
 */

namespace Sau\WP\Plugin\SymKernel\Kernel;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;

abstract class Kernel
{
    private $projectDir;

    protected $container;
    protected $environment;
    protected $debug;


    public function __construct($environment, $debug)
    {
        $this->environment = $environment;
        $this->debug       = $debug;
        $this->boot();

    }

    public function handler()
    {
//        $this->boot();
    }

    public function getProjectDir()
    {
        if (null === $this->projectDir) {
            $r   = new \ReflectionObject($this);
            $dir = $rootDir = \dirname($r->getFileName());
            while ( ! file_exists($dir.'/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    /**
     * @return string
     */
    public function getCacheDir()
    {
        return $this->getProjectDir().'/.var/cache/'.$this->environment;
    }

    /**
     * @return string
     */
    public function getLogDir()
    {
        return $this->getProjectDir().'/.var/logs';
    }

    /**
     * @return string
     */
    public function getCharset()
    {
        return 'UTF-8';
    }

    /**
     * Initializes the service container.
     *
     * The cached version of the service container is used when fresh, otherwise the
     * container is built.
     *
     * @throws \ReflectionException
     */
    protected function initializeContainer()
    {
        $class        = $this->getContainerClass();
        $cacheDir     = $this->getCacheDir();
        $cache        = new ConfigCache($cacheDir.'/'.$class.'.php', $this->debug);
        $oldContainer = null;
        if ($fresh = $cache->isFresh()) {
            // Silence E_WARNING to ignore "include" failures - don't use "@" to prevent silencing fatal errors
            $errorLevel = error_reporting(\E_ALL ^ \E_WARNING);
            $fresh      = $oldContainer = false;
            try {
                if (file_exists($cache->getPath()) && \is_object($this->container = include $cache->getPath())) {
                    $this->container->set('kernel', $this);
                    $oldContainer = $this->container;
                    $fresh        = true;
                }
            } catch (\Throwable $e) {
            } finally {
                error_reporting($errorLevel);
            }
        }

        if ($fresh) {
            return;
        }

        if ($this->debug) {
            $collectedLogs   = array();
            $previousHandler = \defined('PHPUNIT_COMPOSER_INSTALL');
            $previousHandler = $previousHandler ?: set_error_handler(function ($type, $message, $file, $line) use (
                &
                $collectedLogs,
                &$previousHandler
            ) {
                if (E_USER_DEPRECATED !== $type && E_DEPRECATED !== $type) {
                    return $previousHandler ? $previousHandler($type, $message, $file, $line) : false;
                }

                if (isset($collectedLogs[ $message ])) {
                    ++$collectedLogs[ $message ][ 'count' ];

                    return;
                }

                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                // Clean the trace by removing first frames added by the error handler itself.
                for ($i = 0; isset($backtrace[ $i ]); ++$i) {
                    if (isset($backtrace[ $i ][ 'file' ], $backtrace[ $i ][ 'line' ]) && $backtrace[ $i ][ 'line' ] === $line && $backtrace[ $i ][ 'file' ] === $file) {
                        $backtrace = \array_slice($backtrace, 1 + $i);
                        break;
                    }
                }

                $collectedLogs[ $message ] = array(
                    'type'    => $type,
                    'message' => $message,
                    'file'    => $file,
                    'line'    => $line,
                    'trace'   => $backtrace,
                    'count'   => 1,
                );
            });
        }

        try {
            $container = null;
            $container = $this->buildContainer();
            $container->compile();
        } finally {
            if ($this->debug && true !== $previousHandler) {
                restore_error_handler();

                file_put_contents($cacheDir.'/'.$class.'Deprecations.log', serialize(array_values($collectedLogs)));
                file_put_contents($cacheDir.'/'.$class.'Compiler.log', null !== $container ? implode("\n",
                    $container->getCompiler()
                              ->getLog()) : '');
            }
        }

        if (null === $oldContainer && file_exists($cache->getPath())) {
            $errorLevel = error_reporting(\E_ALL ^ \E_WARNING);
            try {
                $oldContainer = include $cache->getPath();
            } catch (\Throwable $e) {
            } finally {
                error_reporting($errorLevel);
            }
        }
        $oldContainer = \is_object($oldContainer) ? new \ReflectionClass($oldContainer) : false;

        $this->dumpContainer($cache, $container, $class, $this->getContainerBaseClass());
        $this->container = require $cache->getPath();
        $this->container->set('kernel', $this);

        if ($oldContainer && \get_class($this->container) !== $oldContainer->name) {
            // Because concurrent requests might still be using them,
            // old container files are not removed immediately,
            // but on a next dump of the container.
            static $legacyContainers = array();
            $oldContainerDir                                = \dirname($oldContainer->getFileName());
            $legacyContainers[ $oldContainerDir.'.legacy' ] = true;
            foreach (glob(\dirname($oldContainerDir).\DIRECTORY_SEPARATOR.'*.legacy') as $legacyContainer) {
                if ( ! isset($legacyContainers[ $legacyContainer ]) && @unlink($legacyContainer)) {
                    (new Filesystem())->remove(substr($legacyContainer, 0, -7));
                }
            }

            touch($oldContainerDir.'.legacy');
        }

//        if ($this->container->has('cache_warmer')) {
//            $this->container->get('cache_warmer')
//                            ->warmUp($this->container->getParameter('kernel.cache_dir'));
//        }
    }

    /**
     * Builds the service container.
     *
     * @return ContainerBuilder The compiled service container
     *
     * @throws \RuntimeException
     */
    protected function buildContainer()
    {
        foreach (array('cache' => $this->getCacheDir(), 'logs' => $this->getLogDir()) as $name => $dir) {
            if ( ! is_dir($dir)) {
                if (false === @mkdir($dir, 0777, true) && ! is_dir($dir)) {
                    throw new \RuntimeException(sprintf("Unable to create the %s directory (%s)\n", $name, $dir));
                }
            } elseif ( ! is_writable($dir)) {
                throw new \RuntimeException(sprintf("Unable to write in the %s directory (%s)\n", $name, $dir));
            }
        }

        $container = $this->getContainerBuilder();
        $container->addObjectResource($this);
        $this->prepareContainer($container);#prepare bundles

        if (null !== $cont = $this->registerContainerConfiguration($this->getContainerLoader($container))) {
            $container->merge($cont);
        }

        //		$container->addCompilerPass( new AddAnnotatedClassesToCachePass( $this ) );

        return $container;
    }

    /**
     * Gets the container class.
     *
     * @return string The container class
     */
    protected function getContainerClass()
    {
        return ucfirst($this->environment).($this->debug ? 'Debug' : '').'ProjectContainer';
    }

    private function boot()
    {

    }

    /**
     * Gets a new ContainerBuilder instance used to build the service container.
     *
     * @return ContainerBuilder
     */
    protected function getContainerBuilder()
    {
        $container = new ContainerBuilder();
        $container->getParameterBag()
                  ->add($this->getKernelParameters());

        if ($this instanceof CompilerPassInterface) {
            $container->addCompilerPass($this, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10000);
        }

        return $container;
    }

    /**
     * Returns the kernel parameters.
     *
     * @return array An array of kernel parameters
     */
    protected function getKernelParameters()
    {
        //		$bundles = array();
        //		$bundlesMetadata = array();
        //
        //		foreach ($this->bundles as $name => $bundle) {
        //			$bundles[$name] = \get_class($bundle);
        //			$bundlesMetadata[$name] = array(
        //				'path' => $bundle->getPath(),
        //				'namespace' => $bundle->getNamespace(),
        //			);
        //		}

        return array(
            'kernel.project_dir'     => realpath($this->getProjectDir()) ?: $this->getProjectDir(),
            'kernel.environment'     => $this->environment,
            'kernel.debug'           => $this->debug,
            'kernel.cache_dir'       => realpath($this->getCacheDir()),
            'kernel.logs_dir'        => realpath($this->getLogDir()) ?: $this->getLogDir(),
            //			'kernel.bundles' => $bundles,
            //			'kernel.bundles_metadata' => $bundlesMetadata,
            'kernel.charset'         => $this->getCharset(),
            'kernel.container_class' => $this->getContainerClass(),
        );
    }

    /**
     * Prepares the ContainerBuilder before it is compiled.
     */
    protected function prepareContainer(ContainerBuilder $container)
    {
        /*
        $extensions = array();
        foreach ($this->bundles as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                $container->registerExtension($extension);
            }

            if ($this->debug) {
                $container->addObjectResource($bundle);
            }
        }

        foreach ($this->bundles as $bundle) {
            $bundle->build($container);
        }

        $this->build($container);

        foreach ($container->getExtensions() as $extension) {
            $extensions[] = $extension->getAlias();
        }

        // ensure these extensions are implicitly loaded
        $container->getCompilerPassConfig()->setMergePass(new MergeExtensionConfigurationPass($extensions));
        */
    }

    /**
     * Returns a loader for the container.
     *
     * @param ContainerInterface $container
     *
     * @return DelegatingLoader The loader
     */
    protected function getContainerLoader(ContainerInterface $container)
    {
        $locator  = new FileLocator($this);
        $resolver = new LoaderResolver(array(
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ));

        return new DelegatingLoader($resolver);
    }

    /**
     * Gets the container's base class.
     *
     * All names except Container must be fully qualified.
     *
     * @return string
     */
    protected function getContainerBaseClass()
    {
        return 'Container';
    }

    abstract protected function registerContainerConfiguration(LoaderInterface $loader);

    /**
     * Dumps the service container to PHP code in the cache.
     *
     * @param ConfigCache      $cache     The config cache
     * @param ContainerBuilder $container The service container
     * @param string           $class     The name of the class to generate
     * @param string           $baseClass The name of the container's base class
     */
    protected function dumpContainer(ConfigCache $cache, ContainerBuilder $container, $class, $baseClass)
    {
        // cache the container
        $dumper = new PhpDumper($container);

        $content = $dumper->dump(array(
            'class'      => $class,
            'base_class' => $baseClass,
            'file'       => $cache->getPath(),
            'as_files'   => true,
            'debug'      => $this->debug,
            'build_time' => $container->hasParameter('kernel.container_build_time') ? $container->getParameter('kernel.container_build_time') : time(),
        ));

        $rootCode = array_pop($content);
        $dir      = \dirname($cache->getPath()).'/';
        $fs       = new Filesystem();

        foreach ($content as $file => $code) {
            $fs->dumpFile($dir.$file, $code);
            @chmod($dir.$file, 0666 & ~umask());
        }
        @unlink(\dirname($dir.$file).'.legacy');

        $cache->write($rootCode, $container->getResources());
    }
}
