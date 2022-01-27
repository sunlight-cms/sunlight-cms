<?php

namespace Sunlight\Plugin\Type;

use Kuria\Options\Option;
use Kuria\Options\Resolver;
use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Plugin\PluginOptionNormalizer;

class ExtendPluginType extends PluginType
{
    function getName(): string
    {
        return 'extend';
    }

    function getDir(): string
    {
        return 'plugins/extend';
    }

    function getClass(): string
    {
        return ExtendPlugin::class;
    }

    function getDefaultBaseNamespace(): string
    {
        return 'SunlightExtend';
    }

    protected function configureOptionResolver(Resolver $optionResolver): void
    {
        parent::configureOptionResolver($optionResolver);

        $eventSubscriberOptions = [
            Option::string('event'),
            Option::string('method')->default(null),
            Option::any('callback')->default(null),
            Option::int('priority')->default(0),
        ];

        $methodOrCallbackValidator = function (array $subscribers) {
            foreach ($subscribers as $subscriber) {
                if (!($subscriber['method'] === null xor $subscriber['callback'] === null)) {
                    return 'either method or callback must be specified';
                }
            }
        };

        $optionResolver->addOption(
            Option::nodeList('events', ...$eventSubscriberOptions)
                ->validate($methodOrCallbackValidator),
            Option::nodeList('events.web', ...$eventSubscriberOptions)
                ->validate($methodOrCallbackValidator),
            Option::nodeList('events.admin', ...$eventSubscriberOptions)
                ->validate($methodOrCallbackValidator),
            Option::list('scripts', 'string')
                ->normalize([PluginOptionNormalizer::class, 'normalizePathArray'])
                ->default([]),
            Option::list('scripts.web', 'string')
                ->normalize([PluginOptionNormalizer::class, 'normalizePathArray'])
                ->default([]),
            Option::list('scripts.admin', 'string')
                ->normalize([PluginOptionNormalizer::class, 'normalizePathArray'])
                ->default([])
                ->notEmpty(),
            Option::nodeList(
                'routes',
                Option::string('pattern'),
                Option::string('method')->default(null),
                Option::any('callback')->default(null)
            )->validate($methodOrCallbackValidator),
            Option::list('langs', 'string')
                ->normalize([PluginOptionNormalizer::class, 'normalizePathArray'])
                ->default([])
        );
    }
}
