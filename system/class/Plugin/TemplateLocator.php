<?php

namespace Sunlight\Plugin;

use Sunlight\Core;

/**
 * Template plugin locator
 */
abstract class TemplateLocator
{
    const UID_TEMPLATE = 0;
    const UID_TEMPLATE_LAYOUT = 1;
    const UID_TEMPLATE_LAYOUT_SLOT = 2;

    /**
     * Check if a template exists
     *
     * @param string $idt
     * @return bool
     */
    static function templateExists(string $idt): bool
    {
        return Core::$pluginManager->has(PluginManager::TEMPLATE, $idt);
    }

    /**
     * Get a template for the given template name
     *
     * @param string $name
     * @return TemplatePlugin
     */
    static function getTemplate(string $name): TemplatePlugin
    {
        return Core::$pluginManager->getTemplate($name);
    }

    /**
     * Get default template
     *
     * @return TemplatePlugin
     */
    static function getDefaultTemplate(): TemplatePlugin
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
    static function composeUid(string $template, ?string $layout = null, ?string $slot = null): string
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
     * @param int    $type see TemplateLocator::UID_* constants
     * @return string[] template, [layout], [slot]
     */
    static function parseUid(string $uid, int $type): array
    {
        $expectedComponentCount = $type + 1;

        return explode(':', $uid, $expectedComponentCount) + array_fill(0, $expectedComponentCount, '');
    }

    /**
     * Get components identified by the given unique template component identifier
     *
     * @param string $uid
     * @param int    $type see TemplateLocator::UID_* constants
     * @return array|null array or null if the given identifier is not valid
     */
    static function getComponentsByUid(string $uid, int $type): ?array
    {
        return call_user_func_array(
            [get_called_class(), 'getComponents'],
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
    static function getComponents(string $template, ?string $layout = null, ?string $slot = null): ?array
    {
        if (!static::templateExists($template)) {
            return null;
        }

        $template = static::getTemplate($template);

        $components = [
            'template' => $template,
        ];

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
    static function getComponentLabel(array $components): string
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
}
