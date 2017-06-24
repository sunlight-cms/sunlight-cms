<?php

namespace Sunlight\Plugin;

use Sunlight\Core;

/**
 * Template plugin locator
 */
class TemplateLocator
{
    const UID_TEMPLATE = 0;
    const UID_TEMPLATE_LAYOUT = 1;
    const UID_TEMPLATE_LAYOUT_SLOT = 2;

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
     * Compose unique template component identifier
     *
     * @param string      $template
     * @param string|null $layout
     * @param string|null $slot
     * @return string
     */
    public static function composeUid($template, $layout = null, $slot = null)
    {
        $uid = $template;

        if ($layout !== null || $slot !== null) {
            $uid .= ':' . $layout;
        }
        if ($slot !== null) {
            $uid .= ':' . $slot;
        }

        return $uid;
    }

    /**
     * Parse the given unique template component identifier
     *
     * @param string $uid
     * @param int    $type see TemplateHelper::UID_* constants
     * @return string[] template, [layout], [slot]
     */
    public static function parseUid($uid, $type)
    {
        $expectedComponentCount = $type + 1;

        return explode(':', $uid, $expectedComponentCount) + array_fill(0, $expectedComponentCount, '');
    }

    /**
     * Get components identified by the given unique template component identifier
     *
     * @param string $uid
     * @param int    $type see TemplateHelper::UID_* constants
     * @return array|null array or null if the given identifier is not valid
     */
    public static function getComponentsByUid($uid, $type)
    {
        return call_user_func_array(
            array(get_called_class(), 'getComponents'),
            static::parseUid($uid, $type)
        );
    }

    /**
     * Get template components
     *
     * Returns an array with the following keys or NULL if the given
     * combination does not exist.
     *
     *      template => (object) instance of TemplatePlugin
     *      layout   => (string) layout identifier (only if $layout is not NULL)
     *      slot     => (string) slot identifier (only if both $layout and $slot are not NULL)
     *
     * @param string      $template
     * @param string|null $layout
     * @param string|null $slot
     * @return array|null array or null if the given combination does not exist
     */
    public static function getComponents($template, $layout = null, $slot = null)
    {
        if (!static::templateExists($template)) {
            return null;
        }

        $template = static::getTemplate($template);

        $components = array(
            'template' => $template,
        );

        if ($layout !== null) {
            if (!$template->hasLayout($layout)) {
                return null;
            }

            $components['layout'] = $layout;
        }

        if ($slot !== null && $layout !== null) {
            if (!$template->hasSlot($layout, $slot)) {
                return null;
            }

            $components['slot'] = $slot;
        }

        return $components;
    }

    /**
     * Get label for the given components
     *
     * @see TemplateLocator::getComponents()
     *
     * @param array $components
     * @return string
     */
    public static function getComponentLabel(array $components)
    {
        $label = $components['template']->getOption('name');

        if (isset($components['layout'])) {
            $label .= ' - ' . $components['template']->getLayoutLabel($components['layout']);
        }
        if (isset($components['layout'], $components['slot'])) {
            $label .= ' - ' . $components['template']->getSlotLabel($components['layout'], $components['slot']);
        }

        return $label;
    }

    /**
     * Verify the given unique layout identifier
     *
     * @deprecated
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
     * @deprecated
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
     * @deprecated
     * @param string|null $layoutUid
     * @return string
     */
    public static function getLayoutUidLabel($layoutUid)
    {
        if ($layoutUid === null) {
            return static::getLayoutUidLabel(_default_template);
        } elseif (static::validateLayoutUid($layoutUid)) {
            list($template, $layout) = static::getTemplateAndLayout($layoutUid);

            return sprintf('%s - %s', $template->getOption('name'), $template->getLayoutLabel($layout));
        } else {
            return $layoutUid;
        }
    }
}
