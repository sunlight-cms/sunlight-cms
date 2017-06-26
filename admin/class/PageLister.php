<?php

namespace Sunlight\Admin;

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Page\PageManager;
use Sunlight\Plugin\TemplateService;
use Sunlight\Util\Url;

class PageLister
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
    private static $ppageTypes;

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Initialize
     */
    public static function init()
    {
        if (static::$initialized) {
            return;
        }
        static::$initialized = true;

        // load config
        static::$config = array();
        $sessionKey = static::getSessionKey();
        if (isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])) {
            static::$config = $_SESSION[$sessionKey];
        }

        // set defaults
        static::$config += array(
            'mode' => _adminpagelist_mode,
            'current_page' => null,
        );

        // fetch types
        static::$pageTypes = PageManager::getTypes();
        static::$ppageTypes = PageManager::getPluginTypes();

        // setup
        static::setup();
    }

    /**
     * Setup
     */
    private static function setup()
    {
        // set current page
        $pageId = _get('page_id', null);
        if ($pageId !== null) {
            if ($pageId === 'root') {
                $pageId = null;
            } else {
                $pageId = (int) $pageId;
            }

            static::setConfig('current_page', $pageId);
        }

        // set mode
        $mode = _get('list_mode', null);
        if ($mode !== null) {
            switch ($mode) {
                case 'tree':
                    static::setConfig('mode', static::MODE_FULL_TREE);
                    break;
                case 'single':
                    static::setConfig('mode', static::MODE_SINGLE_LEVEL);
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
    public static function setConfig($name, $value)
    {
        if (!array_key_exists($name, static::$config)) {
            throw new \OutOfBoundsException(sprintf('Unknown option "%s"', $name));
        }

        static::$config[$name] = $value;
        $_SESSION[static::getSessionKey()][$name] = $value;
    }

    /**
     * Get config value
     *
     * @param string $name
     * @return mixed
     */
    public static function getConfig($name)
    {
        if (!array_key_exists($name, static::$config)) {
            throw new \OutOfBoundsException(sprintf('Unknown option "%s"', $name));
        }

        return static::$config[$name];
    }

    /**
     * Save ord changes
     *
     * @return bool
     */
    public static function saveOrd()
    {
        if (isset($_POST['ord']) && is_array($_POST['ord']) && !isset($_POST['reset'])) {
            $changeset = array();

            foreach ($_POST['ord'] as $id => $ord) {
                $changeset[$id] = array('ord' => (int) $ord);
            }

            DB::updateSetMulti(_root_table, 'id', $changeset);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Get session key
     *
     * @return string
     */
    private static function getSessionKey()
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
    public static function render(array $options = array())
    {
        // default options
        $options += array(
            'mode' => static::$config['mode'],
            'actions' => true,
            'links' => true,
            'type' => false,
            'flags' => false,
            'sortable' => false,
            'title_editable' => false,
            'level_class' => null,
            'breadcrumbs' => true,
        );

        // check current page
        if (static::$config['current_page'] !== null && !DB::count(_root_table, 'id=' . DB::val(static::$config['current_page']))) {
            static::$config['current_page'] = null;
        }

        // container start
        $output = "<div class=\"page-list-container\">\n";

        // breadcrumbs
        if ($options['breadcrumbs'] && static::$config['current_page'] !== null) {
            static::renderBreadcrumbs($output);
        }

        // list
        static::renderList($output, $options);

        // container end
        $output .= "</div>\n";

        return $output;
    }

    /**
     * Render breadcrumbs
     *
     * @param string &$output
     */
    private static function renderBreadcrumbs(&$output)
    {
        $url = Url::current();

        $output .= "<ul class=\"page-list-breadcrumbs\">\n";
        $output .= "<li><a href=\"" . _e($url->set('page_id', 'root')->generateRelative()) . "\">" . _lang('global.all') . "</a></li>\n";
        $path = PageManager::getPath(static::$config['current_page'], null, array('level_inherit', 'layout', 'layout_inherit'));
        foreach ($path as $page) {
            $output .= "<li>" . static::renderPageFlags($page) . "<a href=\"" . _e($url->set('page_id', $page['id'])->generateRelative()) . "\" title=\"ID: {$page['id']}, " . _lang('admin.content.form.ord') . " {$page['ord']}\">{$page['title']}</a></li>\n";
        }
        $output .= "</ul>\n";
    }

    /**
     * Render list
     *
     * @param string &$output
     * @param array  $options
     */
    private static function renderList(&$output, array $options)
    {
        // start
        $class = 'page-list';
        if ($options['sortable']) {
            $output .= "<form method=\"post\">\n";
            if (static::saveOrd()) {
                $output .= _msg(_msg_ok, _lang('admin.content.form.ord.saved'));
            }
        }
        if (static::MODE_SINGLE_LEVEL == $options['mode']) {
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
        $tree = static::filterTree(
            PageManager::getChildren(
                static::$config['current_page'],
                static::MODE_SINGLE_LEVEL == $options['mode']
                    ? (static::$config['current_page'] !== null ? 1 : 0)
                    : null,
                true,
                null,
                array('layout', 'layout_inherit', 'level_inherit', 'ord')
            ),
            $options
        );

        // render mode
        switch ($options['mode']) {
            case static::MODE_FULL_TREE:
                if ($options['level_class'] === null) {
                    $options['level_class'] = true;
                }
                if ($options['sortable']) {
                    throw new \RuntimeException('The "sortable" option is not supported in full tree list mode');
                }
                static::renderFullTree($output, $tree, $options);
                break;
            case static::MODE_SINGLE_LEVEL:
                static::renderSingleLevel($output, $tree, $options);
                break;
            default:
                throw new \OutOfBoundsException('Invalid mode');
        }

        // end
        $output .= "</tbody>\n</table>\n";
        if ($options['sortable']) {
            $output .= "<p class=\"separated\">
                <input type=\"submit\" value=\"" . _lang('global.savechanges') . "\">
                <input type=\"submit\" name=\"reset\" value=\"" . _lang('global.reset') . "\">
            </p>";

            $output .= _xsrfProtect() . "</form>";
        }
    }

    /**
     * Render full tree
     *
     * @param string &$output
     * @param array  $tree
     * @param array  $options
     */
    private static function renderFullTree(&$output, array $tree, array $options)
    {
        if (!empty($tree)) {
            // determine level offset
            if (static::$config['current_page'] !== null) {
                $firstPage = current($tree);
                $levelOffset = -$firstPage['node_level'];
            } else {
                $levelOffset = 0;
            }

            // render
            $even = true;
            foreach ($tree as $page) {
                static::renderPage($output, $page, $options, $even ? 'even' : 'odd', $levelOffset);
                $even = !$even;
            }
        }
    }

    /**
     * Render single level
     *
     * @param string &$output
     * @param array  $tree
     * @param array  $options
     */
    private static function renderSingleLevel(&$output, array $tree, $options)
    {
        $even = true;
        foreach ($tree as $page) {
            static::renderPage($output, $page, $options, $even ? 'even' : 'odd');
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
    private static function filterTree(array $tree, array $options)
    {
        $ids = array_keys($tree);
        $current = 0;
        $filteredTree = array();
        $isFullTree = (static::MODE_FULL_TREE == static::$config['mode']);

        // iterate pages
        foreach ($tree as $id => $page) {
            if (!$isFullTree && $page['node_parent'] != static::$config['current_page']) {
                // not in current branch
                $keep = false;
            } elseif ($isAccessible = static::isAccessible($page)) {
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
                        if (static::isAccessible($tree[$ids[$i]])) {
                            $keep = true;
                            break;
                        }
                    }
                }
            }

            if ($keep) {
                $filteredTree[$id] = array('_is_accessible' => $isAccessible) + $page;
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
    private static function isAccessible(array $page)
    {
        $userHasRight = _userHasPriv('admin' . static::$pageTypes[$page['type']]);
        $isAccessible = $userHasRight;

        Extend::call('admin.root.list.access', array(
            'page' => $page,
            'user_has_right' => $userHasRight,
            'is_accessible' => &$isAccessible,
        ));

        return $isAccessible;
    }

    /**
     * Render page
     *
     * @param string &$output
     * @param array  $page
     * @param array  $options
     * @param string $class
     * @param int    $levelOffset
     */
    private static function renderPage(&$output, array $page, array $options, $class = '', $levelOffset = 0)
    {
        // prepare
        $typeName = static::$pageTypes[$page['type']];
        $isAccessible = $page['_is_accessible'];
        Extend::call('admin.root.list.item', array(
            'item' => &$page,
            'options' => &$options,
            'is_accessible' => $isAccessible,
        ));

        // detect separator, compose link
        $isSeparator = ($page['type'] == _page_separator);
        if (!$isSeparator && $options['links'] && $page['node_depth'] > 0) {
            $nodeLink = Url::current()->set('page_id', $page['id'])->generateRelative();
        } else {
            $nodeLink = null;
        }

        // get actions
        $actions = static::getPageActions($page, $isAccessible);

        // compose class
        if ($class !== '') {
            $class .= ' ';
        }
        $class .= 'page-' . static::$pageTypes[$page['type']];

        if ($page['type'] == _page_plugin && isset(static::$ppageTypes[$page['type_idt']])) {
            $class .= ' page-'
                . $typeName
                . '-'
                . static::$ppageTypes[$page['type_idt']];
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
            $output .= static::renderPageFlags($page);
            $output .= "</td>\n";
        }

        // type
        if ($options['type']) {
            if ($isSeparator) {
                $typeLabel = '';
            } elseif ($page['type'] == _page_plugin && isset(static::$ppageTypes[$page['type_idt']])) {
                $typeLabel = static::$ppageTypes[$page['type_idt']];
            } else {
                $typeLabel = _lang('page.type.' . static::$pageTypes[$page['type']]);
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
    private static function getPageActions(array $page, $hasAccess)
    {
        $actions = array();

        // edit
        if ($hasAccess) {
            $actions['edit'] = array(
                'url' => 'index.php?p=content-edit' . static::$pageTypes[$page['type']] . '&id=' . $page['id'],
                'icon' => 'images/icons/edit.png',
                'label' => _lang('global.edit'),
                'order' => 50,
            );
        }

        // show
        if ($page['type'] != _page_separator) {
            $actions['show'] = array(
                'url' => _linkRoot($page['id'], $page['slug']),
                'new_window' => true,
                'icon' => 'images/icons/show.png',
                'label' => _lang('global.show'),
                'order' => 100,
            );
        }

        // special actions
        switch ($page['type']) {
            case _page_gallery:
                $actions['gallery_images'] = array(
                    'url' => 'index.php?p=content-manageimgs&g=' . $page['id'],
                    'icon' => 'images/icons/img.png',
                    'label' => _lang('admin.content.form.showpics'),
                    'order' => 150,
                );
                break;

            case _page_category:
                $actions['category_articles'] = array(
                    'url' => 'index.php?p=content-articles-list&cat=' . $page['id'],
                    'icon' => 'images/icons/list.png',
                    'label' => _lang('admin.content.form.showarticles'),
                    'order' => 150,
                );
                break;
        }

        // delete
        if ($hasAccess) {
            $actions['delete'] = array(
                'url' => 'index.php?p=content-delete&id=' . $page['id'],
                'icon' => 'images/icons/delete.png',
                'label' => _lang('global.delete'),
                'order' => 200,
            );
        }

        Extend::call('admin.root.list.actions', array(
            'page' => $page,
            'has_access' => $hasAccess,
            'actions' => &$actions
        ));

        uasort($actions, array(__CLASS__, 'sortActions'));

        return $actions;
    }

    /**
     * [CALLBACK] Sort actions
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    public static function sortActions(array $a, array $b)
    {
        return $a['order'] > $b['order'] ? 1 : -1;
    }

    /**
     * Render page flags
     *
     * @param array $page
     * @return string
     */
    private static function renderPageFlags(array $page)
    {
        $output = '';
        if ($page['type'] != _page_separator) {
            if ($page['id'] == _index_page_id) {
                $iconTitle = _lang('admin.content.form.homepage');
                $output .= "<img src=\"images/icons/home.png\" class=\"icon\" alt=\"{$iconTitle}\" title=\"{$iconTitle}\">";
            }
            if ($page['layout'] !== null && !$page['layout_inherit']) {
                $iconTitle = sprintf(_lang('admin.content.form.layout.setting'), _e(TemplateService::getComponentLabelByUid($page['layout'], TemplateService::UID_TEMPLATE_LAYOUT)));
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
