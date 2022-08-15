<?php

namespace Sunlight\Page;

use Sunlight\Admin\Admin;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Database\TreeReaderOptions;
use Sunlight\Extend;

abstract class PageManipulator
{
    /** Flag zavislosti - podstranky */
    const DEPEND_CHILD_PAGES = 1;
    /** Flag zavislosti - prime */
    const DEPEND_DIRECT = 2;
    /** Flag zavislosti - prime i kdyz existuji podstranky */
    const DEPEND_DIRECT_FORCE = 4;

    /**
     * Ziskat vychozi data pro dany typ stranky
     */
    static function getInitialData(int $type, ?string $type_idt): array
    {
        switch ($type) {
            case Page::SECTION:
                $var1 = 0;
                $var2 = null;
                $var3 = 0;
                $var4 = 0;
                break;
            case Page::CATEGORY:
                $var1 = 1;
                $var2 = null;
                $var3 = 1;
                $var4 = 1;
                break;
            case Page::BOOK:
                $var1 = 1;
                $var2 = null;
                $var3 = 0;
                $var4 = 0;
                break;
            case Page::GALLERY:
                $var1 = null;
                $var2 = null;
                $var3 = null;
                $var4 = null;
                break;
            case Page::GROUP:
                $var1 = 1;
                $var2 = 0;
                $var3 = 0;
                $var4 = 0;
                break;
            case Page::FORUM:
                $var1 = null;
                $var2 = 0;
                $var3 = 1;
                $var4 = 0;
                break;
            default:
                $var1 = null;
                $var2 = null;
                $var3 = null;
                $var4 = null;
                break;
        }

        $data = [
            'title' => '',
            'heading' => '',
            'slug' => '',
            'slug_abs' => 0,
            'description' => '',
            'type' => $type,
            'type_idt' => null,
            'node_parent' => null,
            'perex' => '',
            'ord' => 0,
            'content' => '',
            'visible' => 1,
            'public' => 1,
            'level' => 0,
            'level_inherit' => 1,
            'show_heading' => 1,
            'events' => null,
            'link_url' => null,
            'link_new_window' => 0,
            'layout' => null,
            'layout_inherit' => 0,
            'var1' => $var1,
            'var2' => $var2,
            'var3' => $var3,
            'var4' => $var4,
        ];

        Extend::call('admin.page.initial', [
            'type' => $type,
            'type_idt' => $type_idt,
            'data' => &$data,
        ]);

        return $data;
    }

    /**
     * Pregenerovat identifikatory stranek
     *
     * @param int|null $id ID stranky
     * @param bool $getChangesetMap pouze vratit mapu zmen, nezasahovat do databaze 1/0
     */
    static function refreshSlugs(?int $id, bool $getChangesetMap = false): ?array
    {
        if ($id !== null) {
            $id = self::findFirstTreeMatch($id, 'slug_abs', 1);
        }

        $options = new TreeReaderOptions();
        $options->nodeId = $id;
        $options->columns = ['slug', 'slug_abs'];

        return Page::getTreeManager()->propagate(
            Page::getTreeReader()->getFlatTree($options),
            null,
            function ($baseSlug, $currentPage) {
                if (!$currentPage['slug_abs']) {
                    $slug = PageManipulator::getBaseSlug($currentPage['slug']);

                    if ($baseSlug !== null) {
                        $slug = "{$baseSlug}/{$slug}";
                    }

                    if ($currentPage['slug'] !== $slug) {
                        return ['slug' => $slug];
                    }
                }
            },
            function ($baseSlug, $currentPage) {
                return ($baseSlug !== null && !$currentPage['slug_abs'])
                    ? "{$baseSlug}/" . PageManipulator::getBaseSlug($currentPage['slug'])
                    : $currentPage['slug'];
            },
            $getChangesetMap
        );
    }

    /**
     * Aktualizovat min. uroven stranek
     *
     * @param int|null $id ID stranky
     * @param bool $getChangesetMap pouze vratit mapu zmen, nezasahovat do databaze 1/0
     */
    static function refreshLevels(?int $id, bool $getChangesetMap = false): ?array
    {
        if ($id !== null) {
            $id = self::findFirstTreeMatch($id, 'level_inherit', 0);
        }

        $options = new TreeReaderOptions();
        $options->nodeId = $id;
        $options->columns = ['level', 'level_inherit'];

        return Page::getTreeManager()->propagate(
            Page::getTreeReader()->getFlatTree($options),
            0,
            function ($contextLevel, $currentPage) {
                if ($currentPage['level_inherit'] && $currentPage['level'] != $contextLevel) {
                    return ['level' => $contextLevel];
                }
            },
            function ($contextLevel, $currentPage) {
                if (!$currentPage['level_inherit']) {
                    return $currentPage['level'];
                }
            },
            $getChangesetMap
        );
    }

    /**
     * Aktualizovat layouty stranek
     *
     * @param int|null $id ID stranky
     * @param bool $getChangesetMap pouze vratit mapu zmen, nezasahovat do databaze 1/0
     */
    static function refreshLayouts(?int $id, bool $getChangesetMap = false): ?array
    {
        if ($id !== null) {
            $id = self::findFirstTreeMatch($id, 'layout_inherit', 0);
        }

        $options = new TreeReaderOptions();
        $options->nodeId = $id;
        $options->columns = ['layout', 'layout_inherit'];

        return Page::getTreeManager()->propagate(
            Page::getTreeReader()->getFlatTree($options),
            null,
            function ($contextLayout, $currentPage) {
                if ($currentPage['layout_inherit'] && $currentPage['layout'] !== $contextLayout) {
                    return ['layout' => $contextLayout];
                }
            },
            function ($contextLayout, $currentPage) {
                if (!$currentPage['layout_inherit']) {
                    return $currentPage['layout'];
                }
            },
            $getChangesetMap
        );
    }

    /**
     * Ziskat segment z identifikatoru stranky
     */
    static function getBaseSlug(string $slug): string
    {
        $slugLastSlashPos = mb_strrpos($slug, '/');

        return $slugLastSlashPos !== false
            ? mb_substr($slug, $slugLastSlashPos + 1)
            : $slug;
    }

    /**
     * Smazat danou stranku i se zavislostmi
     *
     * @param array $page stranka, ktera ma byt smazana (id, node_depth, node_parent, type, type_idt)
     * @param bool $recursive mazat i podstranky 1/0
     * @param string|null $error promenna, kam ulozit pripadnou chybovou hlasku
     */
    static function delete(array $page, bool $recursive = false, ?string &$error = null): bool
    {
        // zavislosti
        $flags = self::DEPEND_DIRECT;
        if ($recursive) {
            $flags |= self::DEPEND_CHILD_PAGES;
        }
        if (self::deleteDependencies($page, $flags, $error)) {
            // stranka
            DB::delete('page', 'id=' . $page['id']);

            // obnova stromu od nadrazeneho uzlu / rootu
            Page::getTreeManager()->refresh($page['node_parent']);

            // udalost
            Extend::call('admin.page.delete', ['id' => $page['id'], 'page' => [
                'id' => $page['id'],
                'type' => $page['type'],
                'type_idt' => $page['type_idt'],
            ]]);

            return true;
        }

        if ($error === null) {
            $error = _lang('global.deletefail');
        }

        return false;
    }

    /**
     * Ziskat pocty zavislosti dane stranky
     *
     * @param array $page stranka (id, node_level, node_depth, type, type_idt)
     * @param bool $childPages vypisat podstranky 1/0
     */
    static function listDependencies(array $page, bool $childPages = false): array
    {
        $dependencies = [];

        // dle typu
        switch ($page['type']) {
            case Page::SECTION:
                $dependencies[] = DB::count('post', 'type=' . Post::SECTION_COMMENT . ' AND home=' . DB::val($page['id'])) . " " . _lang('count.comments');
                break;
            case Page::CATEGORY:
                $dependencies[] = DB::count('article', 'home1=' . DB::val($page['id']) . ' AND home2=-1 AND home3=-1') . " " . _lang('count.articles');
                break;
            case Page::BOOK:
                $dependencies[] = DB::count('post', 'type=' . Post::BOOK_ENTRY . ' AND home=' . DB::val($page['id'])) . " " . _lang('count.posts');
                break;
            case Page::GALLERY:
                $dependencies[] = DB::count('gallery_image', 'home=' . DB::val($page['id'])) . " " . _lang('count.images');
                break;
            case Page::FORUM:
                $dependencies[] = DB::count('post', 'type=' . Post::FORUM_TOPIC . ' AND home=' . DB::val($page['id'])) . " " . _lang('count.posts');
                break;
            case Page::PLUGIN:
                Extend::call('page.plugin.' . $page['type_idt'] . '.delete.confirm', [
                    'contents' => &$dependencies,
                    'page' => [
                        'id' => $page['id'],
                        'type' => $page['type'],
                        'type_idt' => $page['type_idt'],
                    ],
                ]);
                break;
        }

        // podstranky
        if ($childPages && $page['node_depth'] > 0) {
            $pageTypes = Page::getTypes();

            foreach (Page::getChildren($page['id'], $page['node_depth']) as $childPage) {
                $dependencies[] = sprintf(
                    '%s%s <small>(%s, <code>%s</code>)</small>',
                    str_repeat('&nbsp;', ($childPage['node_level'] - $page['node_level'] - 1) * 4),
                    $childPage['title'],
                    _lang('page.type.' . $pageTypes[$childPage['type']]),
                    $childPage['slug']
                );
            }
        }

        // zadne polozky
        if (empty($dependencies)) {
            $dependencies[] = _lang('global.nokit');
        }

        return $dependencies;
    }

    /**
     * Smazat zavislosti dane stranky
     *
     * @param array $page stranka, ktera ma byt smazana (id, node_depth, type, type_idt)
     * @param int $flags viz konstanty PageManipulator::DEPEND_X
     * @param string|null $error promenna, kam ulozit pripadnou chybovou hlasku
     */
    static function deleteDependencies(array $page, int $flags, ?string &$error = null): bool
    {
        $deleteChildPages = (($flags & self::DEPEND_CHILD_PAGES) !== 0);
        $deleteDirect = (($flags & self::DEPEND_DIRECT) !== 0);
        $deleteDirectForce = (($flags & self::DEPEND_DIRECT_FORCE) !== 0);

        // kontrola podstranek
        if ($page['node_depth'] > 0 && !$deleteChildPages && !$deleteDirectForce) {
            $error = _lang('page.deletefail.children');

            return false;
        }

        // specialni pripad: plugin stranka
        if ($deleteDirect && $page['type'] == Page::PLUGIN) {
            $handled = false;
            Extend::call('page.plugin.' . $page['type_idt'] . '.delete.do', [
                'handled' => &$handled,
                'page' => [
                    'id' => $page['id'],
                    'type' => $page['type'],
                    'type_idt' => $page['type_idt'],
                ],
            ]);

            if ($handled !== true) {
                $error = _lang('plugin.error', ['%plugin%' => $page['type_idt']]);

                return false;
            }
        }

        // podstranky
        if ($deleteChildPages && $page['node_depth'] > 0) {
            foreach (Page::getChildren($page['id'], 1) as $childPage) {
                if (!self::delete($childPage, true, $error)) {
                    return false;
                }
            }
        }

        // ostatni typy
        if ($deleteDirect) {
            switch ($page['type']) {
                    // komentare v sekcich
                case Page::SECTION:
                    DB::delete('post', 'type=' . Post::SECTION_COMMENT . ' AND home=' . $page['id']);
                    break;

                    // clanky v kategoriich a jejich komentare
                case Page::CATEGORY:
                    $rquery = DB::query("SELECT id,home1,home2,home3 FROM " . DB::table('article') . " WHERE home1=" . $page['id'] . " OR home2=" . $page['id'] . " OR home3=" . $page['id']);
                    while ($item = DB::row($rquery)) {
                        if ($item['home1'] == $page['id'] && $item['home2'] == -1 && $item['home3'] == -1) {
                            DB::delete('post', 'type=' . Post::ARTICLE_COMMENT . ' AND home=' . $item['id']);
                            DB::delete('article', 'id=' . $item['id']);
                            continue;
                        } // delete
                        if ($item['home1'] == $page['id'] && $item['home2'] != -1 && $item['home3'] == -1) {
                            DB::update('article', 'id=' . $item['id'], ['home1' => DB::raw('home2')]);
                            DB::update('article', 'id=' . $item['id'], ['home2' => -1]);
                            continue;
                        } // 2->1
                        if ($item['home1'] == $page['id'] && $item['home2'] != -1 && $item['home3'] != -1) {
                            DB::update('article', 'id=' . $item['id'], ['home1' => DB::raw('home2')]);
                            DB::update('article', 'id=' . $item['id'], ['home2' => DB::raw('home3')]);
                            DB::update('article', 'id=' . $item['id'], ['home3' => -1]);
                            continue;
                        } // 2->1,3->2
                        if ($item['home1'] == $page['id'] && $item['home2'] == -1 && $item['home3'] != -1) {
                            DB::update('article', 'id=' . $item['id'], ['home1' => DB::raw('home3')]);
                            DB::update('article', 'id=' . $item['id'], ['home3' => -1]);

                            continue;
                        } // 3->1
                        if ($item['home1'] != -1 && $item['home2'] == $page['id']) {
                            DB::update('article', 'id=' . $item['id'], ['home2' => -1]);
                            continue;
                        } // 2->x
                        if ($item['home1'] != -1 && $item['home3'] == $page['id']) {
                            DB::update('article', 'id=' . $item['id'], ['home3' => -1]);
                            continue;
                        } // 3->x
                    }
                    break;

                    // prispevky v knihach
                case Page::BOOK:
                    DB::delete('post', 'type=' . Post::BOOK_ENTRY . ' AND home=' . $page['id']);
                    break;

                    // obrazky v galerii
                case Page::GALLERY:
                    Admin::deleteGalleryStorage('home=' . $page['id']);
                    DB::delete('gallery_image', 'home=' . $page['id']);
                    @rmdir(SL_ROOT . 'images/galleries/' . $page['id']);
                    break;

                    // prispevky ve forech
                case Page::FORUM:
                    DB::delete('post', 'type=' . Post::FORUM_TOPIC . ' AND home=' . $page['id']);
                    break;
            }
        }

        return true;
    }

    /**
     * Najit prvni stranku odpovidajici dane podmince (sloupec = hodnota).
     *
     * Hledani probiha od aktualni stranky smerem ke korenu.
     * Pokud neni nalezena zadna polozka, je vracen koren.
     */
    private static function findFirstTreeMatch(int $currentId, string $column, $value): int
    {
        $path = Page::getTreeReader()->getPath([$column], $currentId);

        for ($i = count($path) - 1; $i >= 0; --$i) {
            if ($path[$i][$column] == $value || $i === 0) {
                return $path[$i]['id'];
            }
        }

        return $currentId;
    }
}
