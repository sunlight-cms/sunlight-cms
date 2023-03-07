<?php

namespace Sunlight\Plugin\Type;

use Kuria\Options\Option;
use Kuria\Options\Resolver;
use Sunlight\CallbackHandler;
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

        $optionResolver->addOption(
            $this->createEventSubscribersOption('events'),
            $this->createEventSubscribersOption('events.web'),
            $this->createEventSubscribersOption('events.admin'),
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
                ...CallbackHandler::getDefinitionOptions()
            )->normalize([PluginOptionNormalizer::class, 'normalizeCallbackNodes']),
            Option::list('langs', 'string')
                ->normalize([PluginOptionNormalizer::class, 'normalizePathArray'])
                ->default([]),
            Option::nodeList(
                'hcm',
                ...CallbackHandler::getDefinitionOptions()
            )->normalize([PluginOptionNormalizer::class, 'normalizeCallbackNodes']),
            Option::nodeList(
                'cron',
                Option::int('interval'),
                ...CallbackHandler::getDefinitionOptions()
            )->normalize([PluginOptionNormalizer::class, 'normalizeCallbackNodes'])
        );
    }
}
