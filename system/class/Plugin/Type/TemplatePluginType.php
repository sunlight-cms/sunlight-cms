<?php

namespace Sunlight\Plugin\Type;

use Kuria\Options\Node;
use Kuria\Options\Option;
use Kuria\Options\Resolver;
use Sunlight\Plugin\PluginData;
use Sunlight\Plugin\PluginOptionNormalizer;
use Sunlight\Plugin\TemplatePlugin;

class TemplatePluginType extends PluginType
{
    function getName(): string
    {
        return 'template';
    }

    function getDir(): string
    {
        return 'plugins/templates';
    }

    function getClass(): string
    {
        return TemplatePlugin::class;
    }

    function getDefaultBaseNamespace(): string
    {
        return 'SunlightTemplate';
    }

    protected function configureOptionResolver(Resolver $optionResolver): void
    {
        parent::configureOptionResolver($optionResolver);

        $optionResolver->addOption(
            Option::list('css', 'string')
                ->normalize([PluginOptionNormalizer::class, 'normalizeWebPathArray'])
                ->default(function (Node $node, PluginData $plugin) {
                    return [
                        'template_style' => PluginOptionNormalizer::normalizeWebPath('style.css', $plugin),
                    ];
                }),
            Option::list('js', 'string')
                ->normalize([PluginOptionNormalizer::class, 'normalizeWebPathArray'])
                ->default([]),
            Option::bool('responsive')->default(false),
            Option::bool('dark')->default(false),
            Option::bool('bbcode.buttons')->default(true),
            Option::string('box.parent')->default(''),
            Option::string('box.item')->default('div'),
            Option::string('box.title')->default('h3'),
            Option::bool('box.title.inside')->default(false),
            Option::nodeList(
                'layouts',
                Option::string('template')
                    ->normalize([PluginOptionNormalizer::class, 'normalizePath']),
                Option::list('slots', 'string')->default([])
            )
                ->normalize([PluginOptionNormalizer::class, 'normalizeTemplateLayouts'])
                ->required(),
            Option::string('lang_dir')
                ->normalize([PluginOptionNormalizer::class, 'normalizePath'])
                ->default(function (Node $node, PluginData $plugin) {
                    return PluginOptionNormalizer::normalizePath('labels', $plugin);
                }),
            $this->createEventSubscribersOption('events')
        );
    }
}
