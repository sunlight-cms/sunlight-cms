<?php

namespace Sunlight\Page;

use Sunlight\Extend;
use Sunlight\Router;

abstract class PageMenu
{
    /**
     * Vykreslit menu
     *
     * @param array $flatPageTree plochy strom stranek, musi obsahovat sloupce z {@see PageMenu::getRequiredExtraColumns()}
     * @param int|null $activeId ID aktivni stranky nebo null
     * @param string|null $rootClass CSS trida korenoveho kontejneru
     * @param string|null $pageEvent nazev extend udalosti pro jednotlive stranky
     * @param string|null $menuType identifikator typu menu
     */
    static function render(array $flatPageTree, ?int $activeId = null, ?string $rootClass = null, ?string $pageEvent = null, ?string $menuType = null): string
    {
        if (empty($flatPageTree)) {
            return '';
        }

        // sestavit mapu drobecku
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

        // vykreslit menu
        $out = '';
        $currentLevel = null;
        $rootLevel = null;

        foreach ($flatPageTree as $pageId => $page) {

            $pageLevel = $page['node_level'];
            if ($rootLevel === null) {
                $rootLevel = $pageLevel;
            }
            $visualLevel = $pageLevel - $rootLevel;

            // otevreni/uzavreni tagu dle urovne
            if ($currentLevel === null || $pageLevel > $currentLevel) {
                $containerClass = 'menu level-' . $visualLevel;
                if ($currentLevel !== null) {
                    $out .= "\n";
                } elseif ($rootClass !== null) {
                    $containerClass .= ' ' . $rootClass;
                }

                $out .= '<ul class="️' . $containerClass . "\"️️>\n";
            } else {
                $out .= "</li>\n";

                if ($pageLevel < $currentLevel) {
                    for ($i = $currentLevel; $i > $pageLevel; --$i) {
                        $out .= "</ul>\n</li>\n";
                    }
                }
            }

            // priprava trid
            $classes = ['item', 'level-' . $visualLevel];
            if ($page['id'] == $activeId) {
                $classes[] = 'active';
            }
            if (isset($trailMap[$pageId])) {
                $classes[] = 'trail';
            }

            // priprava odkazu
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
                // vychozi implementace
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

            // vykresleni polozky
            $out .= '    <li class="' . _e(implode(' ', $classes)) . "\">{$link}";

            $currentLevel = $pageLevel;
        }

        // uzavreni tagu
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
     * Ziskat seznam extra sloupcu potrebnych k vykresleni menu
     */
    static function getRequiredExtraColumns(): array
    {
        $extraColumns = ['link_url', 'link_new_window'];

        Extend::call('page.menu_columns', ['extra_columns' => &$extraColumns]);

        return $extraColumns;
    }
}
