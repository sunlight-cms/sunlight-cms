<?php

namespace Sunlight\Page;

use Sunlight\Extend;
use Sunlight\Router;

abstract class PageMenu
{
    /**
     * Render a menu
     *
     * @param array $flatPageTree flat page tree, must contain columns from {@see PageMenu::getRequiredExtraColumns()}
     * @param int|null $activeId active page ID or null
     * @param string|null $rootClass container CSS class
     * @param string|null $pageEvent extend event name
     * @param string|null $menuType menu type identifier (for events)
     */
    static function render(array $flatPageTree, ?int $activeId = null, ?string $rootClass = null, ?string $pageEvent = null, ?string $menuType = null): string
    {
        if (empty($flatPageTree)) {
            return '';
        }

        // build trail map
        $trailMap = [];

        if ($activeId !== null) {
            foreach ($flatPageTree as $page) {
                if ($page['id'] == $activeId) {
                    $current = $page;

                    while ($current['node_parent'] !== null && isset($flatPageTree[$current['node_parent']])) {
                        $current = $flatPageTree[$current['node_parent']];
                        $trailMap[$current['id']] = true;
                    }
                }
            }
        }

        // render menu
        $out = '';
        $currentLevel = null;
        $rootLevel = null;

        foreach ($flatPageTree as $pageId => $page) {
            $pageLevel = $page['node_level'];

            if ($rootLevel === null) {
                $rootLevel = $pageLevel;
            }

            $visualLevel = $pageLevel - $rootLevel;

            // open/close tags depending on level
            if ($currentLevel === null || $pageLevel > $currentLevel) {
                $containerClass = 'menu level-' . $visualLevel;

                if ($currentLevel !== null) {
                    $out .= "\n";
                } elseif ($rootClass !== null) {
                    $containerClass .= ' ' . $rootClass;
                }

                $out .= '<ul class="' . $containerClass . "\"️>\n";
            } else {
                $out .= "</li>\n";

                if ($pageLevel < $currentLevel) {
                    for ($i = $currentLevel; $i > $pageLevel; --$i) {
                        $out .= "</ul>\n</li>\n";
                    }
                }
            }

            // prepare classes
            $classes = ['item', 'level-' . $visualLevel];

            if ($page['id'] == $activeId) {
                $classes[] = 'active';
            }

            if (isset($trailMap[$pageId])) {
                $classes[] = 'trail';
            }

            // prepare link
            $url = null;
            $attrs = '';

            if (
                $pageEvent === null
                || ($link = Extend::buffer($pageEvent, [
                    'type' => $menuType,
                    'page' => &$page,
                    'classes' => &$classes,
                    'url' => &$url,
                    'attrs' => &$attrs,
                ])) === ''
            ) {
                // default implementation
                if ($url === null) {
                    if ($page['type'] == Page::LINK) {
                        $url = _e($page['link_url'] ?? '');
                    } else {
                        $url = Router::page($page['id'], $page['slug']);
                    }
                }

                if ($page['type'] == Page::LINK && $page['link_new_window']) {
                    $attrs .= ' target="_blank"';
                }

                $link = '<a href="' . _e($url) . "\"{$attrs}>{$page['title']}</a>";
            }

            // render item
            $out .= '    <li class="' . _e(implode(' ', $classes)) . "\">{$link}";

            $currentLevel = $pageLevel;
        }

        // close tag
        if ($currentLevel !== null) {
            $out .= "</li>\n";

            if ($currentLevel > $rootLevel) {
                for ($i = $currentLevel; $i > $rootLevel; --$i) {
                    $out .= "</ul>\n</li>\n";
                }
            }

            $out .= "</ul>\n";
        }

        return $out;
    }

    /**
     * Get extra columns required for menu rendering
     */
    static function getRequiredExtraColumns(): array
    {
        $extraColumns = ['link_url', 'link_new_window'];

        Extend::call('page.menu_columns', ['extra_columns' => &$extraColumns]);

        return $extraColumns;
    }
}
