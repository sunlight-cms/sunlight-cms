<?php

namespace Sunlight\Plugin\Type;

use Sunlight\Plugin\LanguagePlugin;

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
}
