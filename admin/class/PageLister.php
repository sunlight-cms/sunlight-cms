<?php

namespace Sunlight\Admin;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Page\PageManager;
use Sunlight\Plugin\TemplateService;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

abstract class PageLister
{
    /** Mode - full tree */
    const MODE_FULL_TREE = 0;
    /** Mode - single level */
    const MODE_SINGLE_LEVEL = 1;

    /** @var bool */
    private static $initialized = false;
    /** @var array|null */
    private static $config;
    /** @var array|null */
    private static $pageTypes;
    /** @var array|null */
    private static $pluginTypes;

    /**
     * Initialize
     */
    static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // load config
        self::$config = [];
        $sessionKey = self::getSessionKey();
        if (isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])) {
            self::$config = $_SESSION[$sessionKey];
        }

        // set defaults
        self::$config += [
            'mode' => _adminpagelist_mode,
            'current_page' => null,
        ];

        // fetch types
        self::$pageTypes = PageManager::getTypes();
        self::$pluginTypes = PageManager::getPluginTypes();

        // setup
        self::setup();
    }

    /**
     * Setup
     */
    private static function setup(): void
    {
        // set current page
        $pageId = Request::get('page_id');
        if ($pageId !== null) {
            if ($pageId === 'root') {
                $pageId = null;
            } else {
                $pageId = (int) $pageId;
            }

            self::setConfig('current_page', $pageId);
        }

        // set mode
        $mode = Request::get('list_mode');
        if ($mode !== null) {
            switch ($mode) {
                case 'tree':
                    self::setConfig('mode', self::MODE_FULL_TREE);
                    break;
                case 'single':
                    self::setConfig('mode', self::MODE_SINGLE_LEVEL);
                    break;
            }
        }
    }

    /**
     * Set config value
     *
     * @param string $name
     * @param mixed  $value
     */
    static function setConfig(string $name, $value): void
    {
        if (!array_key_exists($name, self::$config)) {
            throw new \OutOfBoundsException(sprintf('Unknown option "%s"', $name));
        }

        self::$config[$name] = $value;
        $_SESSION[self::getSessionKey()][$name] = $value;
    }

    /**
     * Get config value
     *
     * @param string $name
     * @return mixed
     */
    static function getConfig(string $name)
    {
        if (!array_key_exists($name, self::$config)) {
            throw new \OutOfBoundsException(sprintf('Unknown option "%s"', $name));
        }

        return self::$config[$name];
    }

    /**
     * Save ord changes
     *
     * @return bool
     */
    static function saveOrd(): bool
    {
        if (isset($_POST['ord']) && is_array($_POST['ord']) && !isset($_POST['reset'])) {
            $changeset = [];

            foreach ($_POST['ord'] as $id => $ord) {
                $changeset[$id] = ['ord' => (int) $ord];
            }

            DB::updateSetMulti(_page_table, 'id', $changeset);

            return true;
        }

        return false;
    }

    /**
     * Get session key
     *
     * @return string
     */
    private static function getSessionKey(): string
    {
        return 'admin_page_lister';
    }

    /**
     * Render page list
     *
     * Supported options:
     * ------------------------------------------------------
     * mode             render mode
     * actions          render actions 1/0
     * links            page links 1/0
     * sortable         render as sortable 1/0
     * title_editable   render title as an editable input 1/0
     * level_class      render level class 1/0
     * breadcrumbs      render breadcrumbs 1/0
     *
     * @param array $options
     * @return string
     */
    static function render(array $options = []): string
    {
        // default options
        $options += [
            'mode' => self::$config['mode'],
            'actions' => true,
            'links' => true,
            'type' => false,
            'flags' => false,
            'sortable' => false,
            'title_editable' => false,
            'level_class' => null,
            'breadcrumbs' => true,
        ];

        // check current page
        if (self::$config['current_page'] !== null && !DB::count(_page_table, 'id=' . DB::val(self::$config['current_page']))) {
            self::$config['current_page'] = null;
        }

        // container start
        $output = "<div class=\"page-list-container\">\n";

        // breadcrumbs
        if ($options['breadcrumbs'] && self::$config['current_page'] !== null) {
            self::renderBreadcrumbs($output);
        }

        // list
        self::renderList($output, $options);

        // container end
        $output .= "</div>\n";

        return $output;
    }

    /**
     * Render breadcrumbs
     *
     * @param string $output
     */
    private static function renderBreadcrumbs(string &$output): void
    {
        $rootLink = Core::getCurrentUrl();
        $rootLink->set('page_id', 'root');

        $output .= "<ul class=\"page-list-breadcrumbs\">\n";
        $output .= "<li><a href=\"" . _e($rootLink->buildRelative()) . "\">" . _lang('global.all') . "</a></li>\n";
        $path = PageManager::getPath(self::$config['current_page'], null, ['level_inherit', 'layout', 'layout_inherit']);
        foreach ($path as $page) {
            $pageLink = Core::getCurrentUrl();
            $pageLink->set('page_id', $page['id']);

            $output .= "<li>" . self::renderPageFlags($page) . "<a href=\"" . _e($pageLink->buildRelative()) . "\" title=\"ID: {$page['id']}, " . _lang('admin.content.form.ord') . " {$page['ord']}\">{$page['title']}</a></li>\n";
        }
        $output .= "</ul>\n";
    }

    /**
     * Render list
     *
     * @param string $output
     * @param array  $options
     */
    private static function renderList(string &$output, array $options): void
    {
        // start
        $class = 'page-list';
        if ($options['sortable']) {
            $output .= "<form method=\"post\">\n";
            if (self::saveOrd()) {
                $output .= Message::ok(_lang('admin.content.form.ord.saved'));
            }
        }
        if (self::MODE_SINGLE_LEVEL == $options['mode']) {
            $class .= ' page-list-single-level';
        } else {
            $class .= ' page-list-full-tree';
        }
        $output .= "<table class=\"{$class}\">\n<tbody";
        if ($options['sortable']) {
            $output .= '
    class="sortable"
    data-input-selector="td.page-list-sortcell input"
    data-stopper-selector="tr.page-separator"
    data-handle-selector="td.page-title, .sortable-handle"';
        }
        $output .= ">\n";

        // load and filter tree
        $tree = self::filterTree(
            PageManager::getChildren(
                self::$config['current_page'],
                self::MODE_SINGLE_LEVEL == $options['mode']
                    ? (self::$config['current_page'] !== null ? 1 : 0)
                    : null,
                true,
                null,
                ['layout', 'layout_inherit', 'level_inherit', 'ord']
            ),
            $options
        );

        // render mode
        switch ($options['mode']) {
            case self::MODE_FULL_TREE:
                if ($options['level_class'] === null) {
                    $options['level_class'] = true;
                }
                if ($options['sortable']) {
                    throw new \RuntimeException('The "sortable" option is not supported in full tree list mode');
                }
                self::renderFullTree($output, $tree, $options);
                break;
            case self::MODE_SINGLE_LEVEL:
                self::renderSingleLevel($output, $tree, $options);
                break;
            default:
                throw new \OutOfBoundsException('Invalid mode');
        }

        // end
        $output .= "</tbody>\n</table>\n";
        if ($options['sortable']) {
            $output .= "<p class=\"separated\">
                <input type=\"submit\" value=\"" . _lang('global.savechanges') . "\" accesskey=\"s\">
                <input type=\"submit\" name=\"reset\" value=\"" . _lang('global.reset') . "\">
            </p>";

            $output .= Xsrf::getInput() . "</form>";
        }
    }

    /**
     * Render full tree
     *
     * @param string $output
     * @param array  $tree
     * @param array  $options
     */
    private static function renderFullTree(string &$output, array $tree, array $options): void
    {
        if (!empty($tree)) {
            // determine level offset
            if (self::$config['current_page'] !== null) {
                $firstPage = current($tree);
                $levelOffset = -$firstPage['node_level'];
            } else {
                $levelOffset = 0;
            }

            // render
            $even = true;
            foreach ($tree as $page) {
                self::renderPage($output, $page, $options, $even ? 'even' : 'odd', $levelOffset);
                $even = !$even;
            }
        }
    }

    /**
     * Render single level
     *
     * @param string $output
     * @param array  $tree
     * @param array  $options
     */
    private static function renderSingleLevel(string &$output, array $tree, array $options): void
    {
        $even = true;
        foreach ($tree as $page) {
            self::renderPage($output, $page, $options, $even ? 'even' : 'odd');
            $even = !$even;
        }
    }

    /**
     * Filter tree
     *
     * @param array $tree
     * @param array $options
     * @return array
     */
    private static function filterTree(array $tree, array $options): array
    {
        $ids = array_keys($tree);
        $current = 0;
        $filteredTree = [];
        $isFullTree = (self::MODE_FULL_TREE == self::$config['mode']);

        // iterate pages
        foreach ($tree as $id => $page) {
            if (!$isFullTree && $page['node_parent'] != self::$config['current_page']) {
                // not in current branch
                $keep = false;
            } elseif ($isAccessible = self::isAccessible($page)) {
                // accessible
                $keep = true;
            } else {
                // not accessible
                $keep = false;

                // keep if the tree is sortable
                if ($options['sortable']) {
                    $keep = true;
                } elseif ($page['node_depth'] > 0) {
                    // keep if it has accessible children
                    for ($i = $current + 1; isset($ids[$i]) && $tree[$ids[$i]]['node_level'] > $page['node_level']; ++$i) {
                        if (self::isAccessible($tree[$ids[$i]])) {
                            $keep = true;
                            break;
                        }
                    }
                }
            }

            if ($keep) {
                $filteredTree[$id] = ['_is_accessible' => $isAccessible] + $page;
            }

            ++$current;
        }

        return $filteredTree;
    }

    /**
     * Check whether the page is accessible
     *
     * @param array $page
     * @return bool
     */
    private static function isAccessible(array $page): bool
    {
        $userHasRight = User::hasPrivilege('admin' . self::$pageTypes[$page['type']]);
        $isAccessible = $userHasRight;

        Extend::call('admin.page.list.access', [
            'page' => $page,
            'user_has_right' => $userHasRight,
            'is_accessible' => &$isAccessible,
        ]);

        return $isAccessible;
    }

    /**
     * Render page
     *
     * @param string $output
     * @param array  $page
     * @param array  $options
     * @param string $class
     * @param int    $levelOffset
     */
    private static function renderPage(string &$output, array $page, array $options, string $class = '', int $levelOffset = 0): void
    {
        // prepare
        $typeName = self::$pageTypes[$page['type']];
        $isAccessible = $page['_is_accessible'];
        Extend::call('admin.page.list.item', [
            'item' => &$page,
            'options' => &$options,
            'is_accessible' => $isAccessible,
        ]);

        // detect separator, compose link
        $isSeparator = ($page['type'] == _page_separator);
        if (!$isSeparator && $options['links'] && $page['node_depth'] > 0) {
            $nodeLink = Core::getCurrentUrl();
            $nodeLink->set('page_id', $page['id']);
            $nodeLink = $nodeLink->buildRelative();
        } else {
            $nodeLink = null;
        }

        // get actions
        $actions = self::getPageActions($page, $isAccessible);

        // compose class
        if ($class !== '') {
            $class .= ' ';
        }
        $class .= 'page-' . self::$pageTypes[$page['type']];

        if ($page['type'] == _page_plugin && isset(self::$pluginTypes[$page['type_idt']])) {
            $class .= ' page-'
                . $typeName
                . '-'
                . self::$pluginTypes[$page['type_idt']];
        }

        if (!$isAccessible) {
            $class .= ' page-no-access';
        }

        // render
        $output .= "<tr class=\"{$class}\">\n";

        // order input
        if ($options['sortable']) {
            $output .= "<td class=\"page-list-sortcell\"><span class=\"sortable-handle\"></span><input class=\"inputmini\" type=\"number\" name=\"ord[{$page['id']}]\" value=\"{$page['ord']}\"></td>\n";
        }

        // title
        $output .= "<td class=\"page-title\">";
        $itemAttrs = " title=\"ID: {$page['id']}, " . _lang('admin.content.form.ord') . ": {$page['ord']}\"";
        if ($options['level_class']) {
            $itemAttrs .= " class=\"node-level-p" . ($page['node_level'] + $levelOffset) . "\"";
        }
        if ($nodeLink !== null && !$options['title_editable']) {
            $output .= "<a{$itemAttrs} href=\"" . _e($nodeLink) . "\"><span class=\"page-list-title\">{$page['title']}</span></a>";
        } else {
            $output .= "<span{$itemAttrs}><span class=\"page-list-title\"><span>";
            if ($options['title_editable']) {
                $output .= '<input class="inputbig" maxlength="255" type="text" name="title[' . $page['id'] . ']" value="' . $page['title'] . '">';
            } else {
                $output .= $page['title'];
            }
            $output .= "</span></span>";
        }
        $output .= "</td>\n";

        if ($options['flags']) {
            $output .= '<td>';
            $output .= self::renderPageFlags($page);
            $output .= "</td>\n";
        }

        // type
        if ($options['type']) {
            if ($isSeparator) {
                $typeLabel = '';
            } elseif ($page['type'] == _page_plugin && isset(self::$pluginTypes[$page['type_idt']])) {
                $typeLabel = self::$pluginTypes[$page['type_idt']];
            } else {
                $typeLabel = _lang('page.type.' . self::$pageTypes[$page['type']]);
            }
            $output .= "<td class=\"page-type\">" . $typeLabel . "</td>\n";
        }

        // actions
        if ($options['actions']) {
            $output .= "<td class=\"page-actions\">\n";
            foreach ($actions as $actionId => $action) {
                $actionLabel = _e($action['label']);
                $output .= "<a"
                    . ((isset($action['new_window']) && $action['new_window']) ? ' target="_blank"' : '')
                    . " class=\"page-action-{$actionId}\" href=\"" . _e($action['url']) . "\" title=\"{$actionLabel}\""
                    . '>';
                if (isset($action['icon'])) {
                    $output .= "<img class=\"icon\" src=\"" . _e($action['icon']) . "\" alt=\"{$actionLabel}\">";
                }
                $output .= "<span>{$actionLabel}</span></a>\n";
            }
            $output .= "</td>\n";
        }

        $output .= "</tr>\n";
    }

    /**
     * Prepare page actions
     *
     * @param array $page
     * @param bool  $hasAccess
     * @return array
     */
    private static function getPageActions(array $page, bool $hasAccess): array
    {
        $actions = [];

        // edit
        if ($hasAccess) {
            $actions['edit'] = [
                'url' => 'index.php?p=content-edit' . self::$pageTypes[$page['type']] . '&id=' . $page['id'],
                'icon' => 'images/icons/edit.png',
                'label' => _lang('global.edit'),
                'order' => 50,
            ];
        }

        // show
        if ($page['type'] != _page_separator) {
            $actions['show'] = [
                'url' => Router::page($page['id'], $page['slug']),
                'new_window' => true,
                'icon' => 'images/icons/show.png',
                'label' => _lang('global.show'),
                'order' => 100,
            ];
        }

        // special actions
        switch ($page['type']) {
            case _page_gallery:
                $actions['gallery_images'] = [
                    'url' => 'index.php?p=content-manageimgs&g=' . $page['id'],
                    'icon' => 'images/icons/img.png',
                    'label' => _lang('admin.content.form.showpics'),
                    'order' => 150,
                ];
                break;

            case _page_category:
                $actions['category_articles'] = [
                    'url' => 'index.php?p=content-articles-list&cat=' . $page['id'],
                    'icon' => 'images/icons/list.png',
                    'label' => _lang('admin.content.form.showarticles'),
                    'order' => 150,
                ];
                break;
        }

        // delete
        if ($hasAccess) {
            $actions['delete'] = [
                'url' => 'index.php?p=content-delete&id=' . $page['id'],
                'icon' => 'images/icons/delete.png',
                'label' => _lang('global.delete'),
                'order' => 200,
            ];
        }

        Extend::call('admin.page.list.actions', [
            'page' => $page,
            'has_access' => $hasAccess,
            'actions' => &$actions
        ]);

        uasort($actions, [__CLASS__, 'sortActions']);

        return $actions;
    }

    /**
     * [CALLBACK] Sort actions
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    static function sortActions(array $a, array $b): int
    {
        return $a['order'] > $b['order'] ? 1 : -1;
    }

    /**
     * Render page flags
     *
     * @param array $page
     * @return string
     */
    private static function renderPageFlags(array $page): string
    {
        $output = '';
        if ($page['type'] != _page_separator) {
            if ($page['id'] == _index_page_id) {
                $iconTitle = _lang('admin.content.form.homepage');
                $output .= "<img src=\"images/icons/home.png\" class=\"icon\" alt=\"{$iconTitle}\" title=\"{$iconTitle}\">";
            }
            if ($page['layout'] !== null && !$page['layout_inherit']) {
                $iconTitle = _lang('admin.content.form.layout.setting', ['%layout%' => _e(TemplateService::getComponentLabelByUid($page['layout'], TemplateService::UID_TEMPLATE_LAYOUT))]);
                $output .= "<img src=\"images/icons/template.png\" class=\"icon\" alt=\"{$iconTitle}\" title=\"{$iconTitle}\">";
            }
            if (!$page['public']) {
                $iconTitle = _lang('admin.content.form.private');
                $output .= "<img src=\"images/icons/lock3.png\" class=\"icon\" alt=\"{$iconTitle}\" title=\"{$iconTitle}\">";
            }
            if ($page['level'] > 0) {
                $iconTitle = _lang('admin.content.form.level') . " {$page['level']}+";
                if ($page['level_inherit']) {
                    $icon = 'lock2.png';
                    $iconTitle .= ' (' . _lang('admin.content.form.inherited') . ')';
                } else {
                    $icon = 'lock.png';
                }
                $output .= "<img src=\"images/icons/{$icon}\" class=\"icon\" alt=\"{$iconTitle}\" title=\"{$iconTitle}\">";
            }
            if (!$page['visible']) {
                $iconTitle = _lang('admin.content.form.invisible');
                $output .= "<img src=\"images/icons/eye.png\" class=\"icon\" alt=\"{$iconTitle}\" title=\"{$iconTitle}\">";
            }
        }

        return $output;
    }
}

// static init
PageLister::init();
