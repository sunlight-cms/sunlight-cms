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

        $loader = new TemplateLoader(array(), _root);

        $loader->setPaths(array(''));
        $loader->setPaths(array('plugins/extend'), 'extend');
        $loader->setPaths(array('plugins/languages'), 'languages');
        $loader->setPaths(array('plugins/templates'), 'templates');

        $env = new \Twig_Environment(
            $loader,
            array(
                'debug' => _debug,
                'strict_variables' => _debug,
                'cache' => _root . 'system/cache/twig',
            )
        );

        $env->addGlobal('sl', array(
            'debug' => _debug,
            'root' => _root,
            'url' => Url::current(),
            'logged_in' => _logged_in,
            'user' => Core::$userData,
            'group' => Core::$groupData,
        ));

        $env->addFunction(new \Twig_SimpleFunction('link', '_link'));
        $env->addFunction(new \Twig_SimpleFunction('lang', '_lang'));
        $env->addFunction(new \Twig_SimpleFunction('hcm', '_runHCM', array('is_variadic' => true, 'is_safe' => array('html'))));
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
