<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Settings;

abstract class TemplateService
{
    const UID_TEMPLATE = 0;
    const UID_TEMPLATE_LAYOUT = 1;
    const UID_TEMPLATE_LAYOUT_SLOT = 2;

    /**
     * Check if a template exists
     */
    static function templateExists(string $name): bool
    {
        return Core::$pluginManager->getPlugins()->hasTemplate($name);
    }

    /**
     * Get a template for the given template identifier
     */
    static function getTemplate(string $name): TemplatePlugin
    {
        $template = Core::$pluginManager->getPlugins()->getTemplate($name);

        if ($template === null) {
            self::handleNonexistentTemplate($name);
        }

        return $template;
    }

    /**
     * Get default template
     */
    static function getDefaultTemplate(): TemplatePlugin
    {
        return self::getTemplate(Settings::get('default_template'));
    }

    /**
     * Compose unique template component identifier
     *
     * @param string|TemplatePlugin $template
     */
    static function composeUid($template, ?string $layout = null, ?string $slot = null): ?string
    {
        $uid = $template instanceof TemplatePlugin
            ? $template->getName()
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
     * @param int $type see TemplateService::UID_* constants
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
     * @param int $type see TemplateService::UID_* constants
     */
    static function validateUid(string $uid, int $type): bool
    {
        return self::getComponentsByUid($uid, $type) !== null;
    }

    /**
     * Get components identified by the given unique template component identifier
     *
     * @param int $type see TemplateService::UID_* constants
     * @return array|null array or null if the given identifier is not valid
     */
    static function getComponentsByUid(string $uid, int $type): ?array
    {
        return self::getComponents(...self::parseUid($uid, $type));
    }

    /**
     * Get template components
     *
     * @return array{
     *     template: TemplatePlugin,
     *     layout: string|null,
     *     slot: string|null,
     * }|null
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
     * @param int $type see TemplateService::UID_* constants
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

    /**
     * @return never
     */
    private static function handleNonexistentTemplate(string $name): void
    {
        if (Core::$debug && Core::$pluginManager->getInactivePlugins()->hasTemplate($name)) {
            $plugin = Core::$pluginManager->getInactivePlugins()->getTemplate($name);

            if (!$plugin->isDisabled() && $plugin->hasErrors()) {
                Core::fail(
                    'Motiv "%s" obsahuje chyby:',
                    'Template "%s" contains errors:',
                    [$name],
                    implode("\n", $plugin->getErrors())
                );
            }
        }

        Core::fail(
            'Motiv "%s" není možné použít.',
            'Template "%s" cannot be used.',
            [$name]
        );
    }
}
