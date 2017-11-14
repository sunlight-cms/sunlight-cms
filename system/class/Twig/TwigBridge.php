<?php

namespace Sunlight\Twig;

use Kuria\Debug\Dumper;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Util\Url;

class TwigBridge
{
    /** @var \Twig_Environment|null */
    protected static $env;

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * @return \Twig_Environment
     */
    public static function getEnvironment()
    {
        if (static::$env === null) {
            static::$env = static::createEnvironment();
        }

        return static::$env;
    }

    protected static function createEnvironment()
    {
        if (!Core::isReady()) {
            throw new \LogicException('Cannot use Twig bridge before full system initialization');
        }

        $loader = new \Twig_Loader_Filesystem(array(), _root);

        $loader->setPaths(array('plugins/extend'), 'extend');
        $loader->setPaths(array('plugins/languages'), 'languages');
        $loader->setPaths(array('plugins/templates'), 'templates');

        $env = new \Twig_Environment(
            $loader,
            array(
                'debug' => _dev,
                'strict_variables' => _dev,
                'cache' => _root . 'system/cache/twig',
            )
        );

        $env->addGlobal('sl', array(
            'dev' => _dev,
            'root' => _root,
            'url' => Url::current(),
            'login' => _login,
            'user' => Core::$userData,
            'group' => Core::$groupData,
        ));

        $env->addFunction(new \Twig_SimpleFunction('link', '_link'));
        $env->addFunction(new \Twig_SimpleFunction('dump', array(get_called_class(), 'dump'), array('needs_context' => true)));

        Extend::call('twig.init', array('env' => $env, 'loader' => $loader));

        return $env;
    }

    /**
     * @internal
     */
    public static function dump($context)
    {
        if (func_num_args() > 1) {
            return call_user_func_array(
                array('Kuria\\Debug\\Dumper', 'dump'),
                array_slice(func_get_args(), 1)
            );
        }

        return Dumper::dump($context);
    }
}
