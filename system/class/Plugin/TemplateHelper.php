<?php

namespace Sunlight\Plugin;

use Sunlight\Core;

/**
 * Collection of template-related utility methods
 */
class TemplateHelper
{
    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Check if a template exists
     *
     * @param string $idt
     * @return bool
     */
    public static function templateExists($idt)
    {
        return Core::$pluginManager->has(PluginManager::TEMPLATE, $idt);
    }

    /**
     * Get a template for the given template name
     *
     * @param string $name
     * @return TemplatePlugin
     */
    public static function getTemplate($name)
    {
        return Core::$pluginManager->getTemplate($name);
    }

    /**
     * Get default template
     *
     * @return TemplatePlugin
     */
    public static function getDefaultTemplate()
    {
        return static::getTemplate(_default_template);
    }

    /**
     * Compose an unique layout identifier
     *
     * @param string $template
     * @param string $layout
     * @return string
     */
    public static function composeLayoutUid($template, $layout)
    {
        return "{$template}:{$layout}";
    }

    /**
     * Parse unique layout identifier
     *
     * @param string $layoutUid
     * @return string[] template name, layout name
     */
    public static function parseLayoutUid($layoutUid)
    {
        return explode(':', $layoutUid, 2) + array(1 => TemplatePlugin::DEFAULT_LAYOUT);
    }

    /**
     * Verify the given unique layout identifier
     *
     * @param string $layoutUid
     * @return bool
     */
    public static function validateLayoutUid($layoutUid)
    {
        list($template, $layout) = static::parseLayoutUid($layoutUid);

        return
            Core::$pluginManager->has(PluginManager::TEMPLATE, $template)
            && Core::$pluginManager->getTemplate($template)->hasLayout($layout);
    }

    /**
     * Get template and layout name for the given unique layout identifier
     *
     * @param string $layoutUid
     * @return array TemplatePlugin, layout name
     */
    public static function getTemplateAndLayout($layoutUid)
    {
        list($template, $layout) = static::parseLayoutUid($layoutUid);

        return array(
            Core::$pluginManager->getTemplate($template),
            $layout,
        );
    }

    /**
     * Get label for the given unique layout identifier
     *
     * @param string|null $layoutUid
     * @return string
     */
    public static function getLayoutUidLabel($layoutUid)
    {
        if (null === $layoutUid) {
            return static::getLayoutUidLabel(_default_template);
        } elseif (static::validateLayoutUid($layoutUid)) {
            list($template, $layout) = static::getTemplateAndLayout($layoutUid);

            return $template->getLayoutLabel($layout);
        } else {
            return $layoutUid;
        }
    }
}
