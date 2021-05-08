<?php

namespace Sunlight\Plugin;

use Sunlight\Core;

abstract class TemplateService
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
     * Get a template for the given template identifier
     *
     * @param string $id
     * @return TemplatePlugin
     */
    static function getTemplate(string $id): TemplatePlugin
    {
        return Core::$pluginManager->getTemplate($id);
    }

    /**
     * Get default template
     *
     * @return TemplatePlugin
     */
    static function getDefaultTemplate(): TemplatePlugin
    {
        return self::getTemplate(_default_template);
    }

    /**
     * Compose unique template component identifier
     *
     * @param string|TemplatePlugin $template
     * @param string|null           $layout
     * @param string|null           $slot
     * @return string|null
     */
    static function composeUid($template, ?string $layout = null, ?string $slot = null): ?string
    {
        $uid = $template instanceof TemplatePlugin
            ? $template->getId()
            : $template;

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
     * @param int    $type see TemplateService::UID_* constants
     * @return string[] template, [layout], [slot]
     */
    static function parseUid(string $uid, int $type): array
    {
        $expectedComponentCount = $type + 1;

        return explode(':', $uid, $expectedComponentCount) + array_fill(0, $expectedComponentCount, '');
    }

    /**
     * Verify that the given unique template component identifier is valid
     * and points to existing components
     *
     * @param string $uid
     * @param int    $type see TemplateService::UID_* constants
     * @return bool
     */
    static function validateUid(string $uid, int $type): bool
    {
        return self::getComponentsByUid($uid, $type) !== null;
    }

    /**
     * Get components identified by the given unique template component identifier
     *
     * @param string $uid
     * @param int    $type see TemplateService::UID_* constants
     * @return array|null array or null if the given identifier is not valid
     */
    static function getComponentsByUid(string $uid, int $type): ?array
    {
        return self::getComponents(...self::parseUid($uid, $type));
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
        if (!self::templateExists($template)) {
            return null;
        }

        $template = self::getTemplate($template);

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
     * @param TemplatePlugin $template
     * @param string|null    $layout
     * @param string|null    $slot
     * @param bool           $includeTemplateName
     * @return string
     */
    static function getComponentLabel(TemplatePlugin $template, ?string $layout = null, ?string $slot = null, bool $includeTemplateName = true): string
    {
        $parts = [];

        if ($includeTemplateName) {
            $parts[] = $template->getOption('name');
        }
        if ($layout !== null || $slot !== null) {
            $parts[] = $template->getLayoutLabel($layout);
        }
        if ($slot !== null) {
            $parts[] = $template->getSlotLabel($layout, $slot);
        }

        return implode(' - ', $parts);
    }

    /**
     * Get label for the given component array
     *
     * @see TemplateService::getComponents()
     *
     * @param array $components
     * @param bool  $includeTemplateName
     * @return string
     */
    static function getComponentLabelFromArray(array $components, bool $includeTemplateName = true): string
    {
        return self::getComponentLabel(
            $components['template'],
            $components['layout'] ?? null,
            $components['slot'] ?? null,
            $includeTemplateName
        );
    }

    /**
     * Get label for the given unique template component identifier
     *
     * @param string|null $uid
     * @param int         $type see TemplateService::UID_* constants
     * @param bool        $includeTemplateName
     * @return string
     */
    static function getComponentLabelByUid(?string $uid, int $type, bool $includeTemplateName = true): string
    {
        if ($uid !== null) {
            $components = self::getComponentsByUid($uid, $type);
        } else {
            $components = [
                'template' => self::getDefaultTemplate(),
            ];

            if ($type >= self::UID_TEMPLATE_LAYOUT) {
                $components['layout'] = TemplatePlugin::DEFAULT_LAYOUT;
            }
            if ($type >= self::UID_TEMPLATE_LAYOUT_SLOT) {
                $components['slot'] = '';
            }
        }

        if ($components !== null) {
            return self::getComponentLabelFromArray($components, $includeTemplateName);
        }

        return $uid;
    }
}
