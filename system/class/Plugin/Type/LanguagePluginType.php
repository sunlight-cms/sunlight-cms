<?php

namespace Sunlight\Plugin\Type;

use Kuria\Options\Option;
use Kuria\Options\Resolver;
use Sunlight\Core;
use Sunlight\Plugin\LanguagePlugin;
use Sunlight\Plugin\PluginData;

class LanguagePluginType extends PluginType
{
    function getName(): string
    {
        return 'language';
    }

    function getDir(): string
    {
        return 'plugins/languages';
    }

    function getClass(): string
    {
        return LanguagePlugin::class;
    }

    function getDefaultBaseNamespace(): string
    {
        return 'SunlightLanguage';
    }

    function isPluginAllowedInSafeMode(PluginData $plugin): bool
    {
        return $plugin->name === Core::$fallbackLang;
    }

    protected function configureOptionResolver(Resolver $optionResolver): void
    {
        parent::configureOptionResolver($optionResolver);

        $optionResolver->addOption(
            Option::string('iso_code'),
            Option::string('decimal_point'),
            Option::string('thousands_separator')
        );
    }
}
