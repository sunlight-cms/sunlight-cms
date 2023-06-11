<?php

namespace Sunlight\Page;

use Sunlight\Admin\Admin;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Database\TreeReaderOptions;
use Sunlight\Extend;

abstract class PageManipulator
{
    /** Dependency flag - child pages */
    const DEPEND_CHILD_PAGES = 1;
    /** Dependency flag - direct */
    const DEPEND_DIRECT = 2;
    /** Dependency flag - direct even if subpages child pages exist */
    const DEPEND_DIRECT_FORCE = 4;

    /**
     * Get initial data for a page
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
     * Refresh page slugs
     *
     * @param int|null $id page ID
     */
    static function refreshSlugs(?int $id): ?array
    {
        if ($id !== null) {
            $id = self::findFirstPathMatch($id, 'slug_abs', 1);
        }

        $options = new TreeReaderOptions();
        $options->nodeId = $id;
        $options->columns = ['slug', 'slug_abs'];

        return Page::getTreeManager()->propagate(
            Page::getTreeReader()->getFlatTree($options),
            null,
            function ($baseSlug, $currentPage) {
                if (!$currentPage['slug_abs']) {
                    $slug = PageManipulator::getLastSegment($currentPage['slug']);

                    if ($baseSlug !== null) {
                        $slug = $baseSlug . '/' . $slug;
                    }

                    if ($currentPage['slug'] !== $slug) {
                        return ['slug' => $slug];
                    }
                }
            },
            function ($baseSlug, $currentPage) {
                return ($baseSlug !== null && !$currentPage['slug_abs'])
                    ? "{$baseSlug}/" . PageManipulator::getLastSegment($currentPage['slug'])
                    : $currentPage['slug'];
            }
        );
    }

    /**
     * Refresh page access levels
     *
     * @param int|null $id page ID
     */
    static function refreshLevels(?int $id): ?array
    {
        if ($id !== null) {
            $id = self::findFirstPathMatch($id, 'level_inherit', 0);
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
            }
        );
    }

    /**
     * Refresh page layouts
     *
     * @param int|null $id page ID
     */
    static function refreshLayouts(?int $id): ?array
    {
        if ($id !== null) {
            $id = self::findFirstPathMatch($id, 'layout_inherit', 0);
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
            }
        );
    }

    /**
     * Get last segment from page slug
     */
    static function getLastSegment(string $slug): string
    {
        $slugLastSlashPos = mb_strrpos($slug, '/');

        return $slugLastSlashPos !== false
            ? mb_substr($slug, $slugLastSlashPos + 1)
            : $slug;
    }

    /**
     * Delete the given page, incudling dependencies
     *
     * @param array $page page data (id, node_depth, node_parent, type, type_idt)
     * @param bool $recursive remove child pages
     */
    static function delete(array $page, bool $recursive = false, ?string &$error = null): bool
    {
        $flags = self::DEPEND_DIRECT;

        if ($recursive) {
            $flags |= self::DEPEND_CHILD_PAGES;
        }

        if (self::deleteDependencies($page, $flags, $error)) {
            // delete page
            DB::delete('page', 'id=' . $page['id']);

            // refresh tree from parent (or root)
            Page::getTreeManager()->refresh($page['node_parent']);

            // extend
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
     * List page dependencies
     *
     * @param array $page page data (id, node_level, node_depth, type, type_idt)
     * @param bool $childPages list child pages 1/0
     */
    static function listDependencies(array $page, bool $childPages = false): array
    {
        $dependencies = [];

        switch ($page['type']) {
            case Page::SECTION:
                $dependencies[] = DB::count('post', 'type=' . Post::SECTION_COMMENT . ' AND home=' . DB::val($page['id'])) . ' ' . _lang('count.comments');
                break;
            case Page::CATEGORY:
                $dependencies[] = DB::count('article', 'home1=' . DB::val($page['id']) . ' AND home2=-1 AND home3=-1') . ' ' . _lang('count.articles');
                break;
            case Page::BOOK:
                $dependencies[] = DB::count('post', 'type=' . Post::BOOK_ENTRY . ' AND home=' . DB::val($page['id'])) . ' ' . _lang('count.posts');
                break;
            case Page::GALLERY:
                $dependencies[] = DB::count('gallery_image', 'home=' . DB::val($page['id'])) . ' ' . _lang('count.images');
                break;
            case Page::FORUM:
                $dependencies[] = DB::count('post', 'type=' . Post::FORUM_TOPIC . ' AND home=' . DB::val($page['id'])) . ' ' . _lang('count.posts');
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

        // child pages
        if ($childPages && $page['node_depth'] > 0) {
            foreach (Page::getChildren($page['id'], $page['node_depth']) as $childPage) {
                $dependencies[] = sprintf(
                    '%s%s <small>(%s, <code>%s</code>)</small>',
                    str_repeat('&nbsp;', ($childPage['node_level'] - $page['node_level'] - 1) * 4),
                    $childPage['title'],
                    _lang('page.type.' . Page::TYPES[$childPage['type']]),
                    $childPage['slug']
                );
            }
        }

        // empty?
        if (empty($dependencies)) {
            $dependencies[] = _lang('global.nokit');
        }

        return $dependencies;
    }

    /**
     * Delete dependencies of the given page
     *
     * @param array $page page data (id, node_depth, type, type_idt)
     * @param int $flags see PageManipulator::DEPEND_X constants
     */
    static function deleteDependencies(array $page, int $flags, ?string &$error = null): bool
    {
        $deleteChildPages = (($flags & self::DEPEND_CHILD_PAGES) !== 0);
        $deleteDirect = (($flags & self::DEPEND_DIRECT) !== 0);
        $deleteDirectForce = (($flags & self::DEPEND_DIRECT_FORCE) !== 0);

        // check child pages
        if ($page['node_depth'] > 0 && !$deleteChildPages && !$deleteDirectForce) {
            $error = _lang('page.deletefail.children');

            return false;
        }

        // plugin page
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

        // child pages
        if ($deleteChildPages && $page['node_depth'] > 0) {
            foreach (Page::getChildren($page['id'], 1) as $childPage) {
                if (!self::delete($childPage, true, $error)) {
                    return false;
                }
            }
        }

        // direct dependencies
        if ($deleteDirect) {
            switch ($page['type']) {
                // section comments
                case Page::SECTION:
                    DB::delete('post', 'type=' . Post::SECTION_COMMENT . ' AND home=' . $page['id']);
                    break;

                // category articles and their comments
                case Page::CATEGORY:
                    $rquery = DB::query('SELECT id,home1,home2,home3 FROM ' . DB::table('article') . ' WHERE home1=' . $page['id'] . ' OR home2=' . $page['id'] . ' OR home3=' . $page['id']);

                    while ($item = DB::row($rquery)) {
                        if ($item['home1'] == $page['id'] && $item['home2'] == -1 && $item['home3'] == -1) {
                            // delete article if this is its only category
                            DB::delete('post', 'type=' . Post::ARTICLE_COMMENT . ' AND home=' . $item['id']);
                            DB::delete('article', 'id=' . $item['id']);
                            continue;
                        }

                        if ($item['home1'] == $page['id'] && $item['home2'] != -1 && $item['home3'] == -1) {
                            // move home2 => home
                            DB::update('article', 'id=' . $item['id'], ['home1' => DB::raw('home2')]);
                            DB::update('article', 'id=' . $item['id'], ['home2' => -1]);
                            continue;
                        }

                        if ($item['home1'] == $page['id'] && $item['home2'] != -1 && $item['home3'] != -1) {
                            // move home2 => home, home3 => home2
                            DB::update('article', 'id=' . $item['id'], ['home1' => DB::raw('home2')]);
                            DB::update('article', 'id=' . $item['id'], ['home2' => DB::raw('home3')]);
                            DB::update('article', 'id=' . $item['id'], ['home3' => -1]);
                            continue;
                        }

                        if ($item['home1'] == $page['id'] && $item['home2'] == -1 && $item['home3'] != -1) {
                            // move home3 => home
                            DB::update('article', 'id=' . $item['id'], ['home1' => DB::raw('home3')]);
                            DB::update('article', 'id=' . $item['id'], ['home3' => -1]);

                            continue;
                        }

                        if ($item['home1'] != -1 && $item['home2'] == $page['id']) {
                            // unset home2
                            DB::update('article', 'id=' . $item['id'], ['home2' => -1]);
                            continue;
                        }

                        if ($item['home1'] != -1 && $item['home3'] == $page['id']) {
                            // unset home3
                            DB::update('article', 'id=' . $item['id'], ['home3' => -1]);
                            continue;
                        }
                    }
                    break;

                // book posts
                case Page::BOOK:
                    DB::delete('post', 'type=' . Post::BOOK_ENTRY . ' AND home=' . $page['id']);
                    break;

                // gallery images
                case Page::GALLERY:
                    Admin::deleteGalleryStorage('home=' . $page['id']);
                    DB::delete('gallery_image', 'home=' . $page['id']);
                    @rmdir(SL_ROOT . 'images/galleries/' . $page['id']);
                    break;

                // forum posts
                case Page::FORUM:
                    DB::delete('post', 'type=' . Post::FORUM_TOPIC . ' AND home=' . $page['id']);
                    break;
            }
        }

        return true;
    }

    /**
     * Find first page in path matching the given condition (column = value)
     *
     * The search starts from the current page up to the root.
     * If nothing is found, the root is returned.
     */
    private static function findFirstPathMatch(int $currentId, string $column, $value): int
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
