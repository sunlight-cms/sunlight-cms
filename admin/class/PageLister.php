<?php

namespace Sunlight\Admin;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Plugin\TemplateService;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Util\Form;
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
            'mode' => Settings::get('adminpagelist_mode'),
            'current_page' => null,
        ];

        // fetch plugin types
        self::$pluginTypes = Page::getPluginTypes();

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
     */
    static function saveOrd(): bool
    {
        if (isset($_POST['ord']) && is_array($_POST['ord']) && !isset($_POST['reset'])) {
            $changeset = [];

            foreach ($_POST['ord'] as $id => $ord) {
                $changeset[$id] = ['ord' => (int) $ord];
            }

            DB::updateSetMulti('page', 'id', $changeset);

            return true;
        }

        return false;
    }

    /**
     * Get session key
     */
    private static function getSessionKey(): string
    {
        return 'admin_page_lister';
    }

    /**
     * Render page list
     *
     * Supported options:
     * ------------------
     * - mode                 render mode (defaults to session value)
     * - actions (1)          render actions 1/0
     * - links (1)            page links 1/0
     * - type (0)             render type 1/0
     * - flags (0)            render flags 1/0
     * - sortable (0)         render as sortable 1/0
     * - title_editable (0)   render title as an editable input 1/0
     * - breadcrumbs (1)      render breadcrumbs 1/0
     *
     * @param array{
     *     mode?: int,
     *     actions?: bool,
     *     links?: bool,
     *     type?: bool,
     *     flags?: bool,
     *     sortable?: bool,
     *     title_editable?: bool,
     *     breadcrumbs?: bool,
     * } $options see description
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
            'breadcrumbs' => true,
        ];

        // check current page
        if (self::$config['current_page'] !== null && !DB::count('page', 'id=' . DB::val(self::$config['current_page']))) {
            self::$config['current_page'] = null;
        }

        // container start
        $output = "<div class=\"page-list-container horizontal-scroller\">\n";

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
     */
    private static function renderBreadcrumbs(string &$output): void
    {
        $rootLink = Core::getCurrentUrl();
        $rootLink->set('page_id', 'root');

        $output .= "<ul class=\"page-list-breadcrumbs\">\n";
        $output .= '<li><a href="' . _e($rootLink->buildRelative()) . '">' . _lang('global.all') . "</a></li>\n";
        $path = Page::getPath(self::$config['current_page'], null, ['level_inherit', 'layout', 'layout_inherit']);

        foreach ($path as $page) {
            $pageLink = Core::getCurrentUrl();
            $pageLink->set('page_id', $page['id']);

            $output .= '<li>' . self::renderPageFlags($page) . '<a href="' . _e($pageLink->buildRelative()) . '" title="ID: ' . $page['id'] . ', ' . _lang('admin.content.form.ord') . ' ' . $page['ord'] . '">' . $page['title'] . "</a></li>\n";
        }

        $output .= "</ul>\n";
    }

    /**
     * Render list
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

        if ($options['mode'] == self::MODE_SINGLE_LEVEL) {
            $class .= ' page-list-single-level';
        } else {
            $class .= ' page-list-full-tree';
        }

        $output .= '<table class="' . $class . "\">\n<tbody";

        if ($options['sortable']) {
            $output .= '
    class="sortable"
    data-input-selector="td.page-list-sortcell input"
    data-stopper-selector="tr.page-separator"
    data-handle-selector="td.page-title, .sortable-handle"';
        }

        $output .= ">\n";

        // load and filter tree
        $extraColumns = ['layout', 'layout_inherit', 'level_inherit', 'ord'];

        if ($options['mode'] == self::MODE_FULL_TREE) {
            $tree = Page::getChildren(
                self::$config['current_page'],
                null,
                true,
                new PageFilter(null, true),
                $extraColumns
            );
        } else {
            $tree = Page::getChildren(
                self::$config['current_page'],
                self::$config['current_page'] !== null ? 1 : 0,
                true,
                null,
                $extraColumns
            );
        }

        // render mode
        switch ($options['mode']) {
            case self::MODE_FULL_TREE:
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
            $output .= '<p class="separated">
                ' . Form::input('submit', null, _lang('global.savechanges'), ['accesskey' => 's']) . '
                ' . Form::input('submit', 'reset', _lang('global.reset')) . '
            </p>';

            $output .= Xsrf::getInput() . '</form>';
        }
    }

    /**
     * Render full tree
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
     * Render page
     */
    private static function renderPage(string &$output, array $page, array $options, string $class = '', int $levelOffset = 0): void
    {
        // prepare
        $typeName = Page::TYPES[$page['type']];
        $isAccessible = Admin::pageAccess($page, true);
        Extend::call('admin.page.list.item', [
            'item' => &$page,
            'options' => &$options,
            'is_accessible' => $isAccessible,
        ]);

        // get actions
        $actions = self::getPageActions($page, $isAccessible);

        // compose class
        if ($class !== '') {
            $class .= ' ';
        }

        $class .= 'page-' . Page::TYPES[$page['type']];

        if ($page['type'] == Page::PLUGIN && isset(self::$pluginTypes[$page['type_idt']])) {
            $class .= ' page-'
                . $typeName
                . '-'
                . _e($page['type_idt']);
        }

        if (!$isAccessible) {
            $class .= ' page-no-access';
        }

        if (!$page['visible']) {
            $class .= ' page-invisible';
        }

        // render
        $output .= '<tr class="' . $class . "\">\n";

        // order input
        if ($options['sortable']) {
            $output .= '<td class="page-list-sortcell"><span class="sortable-handle"></span>' . Form::input('text', 'ord[' . $page['id'] . ']', $page['ord'], ['class' => 'page-list-ord']) . "</td>\n";
        }

        // title
        $output .= "<td class=\"page-title\">\n";
        $output .= '<div class="page-title-content node-level-m' . ($page['node_level'] + $levelOffset) . "\">\n";

        if ($options['title_editable']) {
            $output .= Form::input('text', 'title[' . $page['id'] . ']', $page['title'], ['class' => 'inputbig', 'maxlength' => 255], false);
        } else {
            $title = 'ID: ' . $page['id'] . ', ' . _lang('admin.content.form.ord') . ': ' . $page['ord'];

            if ($options['links']) {
                $output .= '<a'
                    . ' href="' . _e(Router::admin('content-edit' . Page::TYPES[$page['type']], ['query' => ['id' => $page['id']]])) . '"'
                    . ' title="' . _e($title) . '"'
                    . '>'
                    . $page['title']
                    . "</a>\n";

                if ($page['node_depth'] > 0) {
                    $subtreeUrl = Core::getCurrentUrl();
                    $subtreeUrl->set('page_id', $page['id']);

                    $output .= "<span class=\"page-actions\">\n"
                        . self::renderAction([
                            'url' => $subtreeUrl->buildRelative(),
                            'icon' => Router::path('admin/public/images/icons/down-arrow' . (Settings::get('adminscheme_dark') ? '-inv' : '') . '.png'),
                            'label' => _lang('admin.content.form.showsubpages'),
                        ])
                        . "</span>\n";
                }
            } else {
                $output .= '<span title="' . _e($title) . '">' . $page['title'] . '</span>';
            }
        }

        $output .= "</div>\n";
        $output .= "</td>\n";

        // flags
        if ($options['flags']) {
            $output .= '<td>';
            $output .= self::renderPageFlags($page);
            $output .= "</td>\n";
        }

        // type
        if ($options['type']) {
            if ($page['type'] == Page::SEPARATOR) {
                $typeLabel = '';
            } elseif ($page['type'] == Page::PLUGIN && isset(self::$pluginTypes[$page['type_idt']])) {
                $typeLabel = self::$pluginTypes[$page['type_idt']];
            } else {
                $typeLabel = _lang('page.type.' . Page::TYPES[$page['type']]);
            }

            $output .= '<td class="page-type">' . $typeLabel . "</td>\n";
        }

        // actions
        if ($options['actions']) {
            $output .= "<td class=\"page-actions\">\n"
                . implode("\n", array_map([__CLASS__, 'renderAction'], $actions))
                . "</td>\n";
        }

        $output .= "</tr>\n";
    }

    /**
     * Prepare page actions
     */
    private static function getPageActions(array $page, bool $hasAccess): array
    {
        $actions = [];

        // type-specific actions
        switch ($page['type']) {
            case Page::GALLERY:
                $actions['gallery_images'] = [
                    'url' => Router::admin('content-manageimgs', ['query' => ['g' => $page['id']]]),
                    'icon' => Router::path('admin/public/images/icons/img.png'),
                    'label' => _lang('admin.content.form.showpics'),
                    'order' => 50,
                ];
                break;

            case Page::CATEGORY:
                $actions['category_articles'] = [
                    'url' => Router::admin('content-articles-list', ['query' => ['cat' => $page['id']]]),
                    'icon' => Router::path('admin/public/images/icons/list.png'),
                    'label' => _lang('admin.content.form.showarticles'),
                    'order' => 50,
                ];
                break;
        }

        // edit
        if ($hasAccess) {
            $actions['edit'] = [
                'url' => Router::admin('content-edit' . Page::TYPES[$page['type']], ['query' => ['id' => $page['id']]]),
                'icon' => Router::path('admin/public/images/icons/edit.png'),
                'label' => _lang('global.edit'),
                'order' => 100,
            ];
        }

        // show
        if ($page['type'] != Page::SEPARATOR) {
            $actions['show'] = [
                'url' => Router::page($page['id'], $page['slug']),
                'new_window' => true,
                'icon' => Router::path('admin/public/images/icons/show.png'),
                'label' => _lang('global.show'),
                'order' => 100,
            ];
        }

        // delete
        if ($hasAccess) {
            $actions['delete'] = [
                'url' => Router::admin('content-delete', ['query' => ['id' => $page['id']]]),
                'icon' => Router::path('admin/public/images/icons/delete.png'),
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

    private static function renderAction(array $action): string
    {
        $actionLabel = _e($action['label']);

        $output = '<a'
            . (!empty($action['new_window']) ? ' target="_blank"' : '')
            . ' href="' . _e($action['url']) . '"'
            . ' title="' . $actionLabel . '"'
            . '>';

        if (isset($action['icon'])) {
            $output .= '<img class="icon" src="' . _e($action['icon']) . '" alt="' . $actionLabel . '">';
        }

        $output .= "<span>{$actionLabel}</span></a>\n";

        return $output;
    }

    private static function sortActions(array $a, array $b): int
    {
        return $a['order'] > $b['order'] ? 1 : -1;
    }

    private static function renderPageFlags(array $page): string
    {
        $output = '';

        if ($page['type'] != Page::SEPARATOR) {
            if ($page['id'] == Settings::get('index_page_id')) {
                $iconTitle = _lang('admin.content.form.homepage');
                $output .= '<img src="' . _e(Router::path('admin/public/images/icons/home.png')) . '" class="icon" alt="' . $iconTitle . '" title="' . $iconTitle . '">';
            }

            if ($page['layout'] !== null && !$page['layout_inherit']) {
                $iconTitle = _lang('admin.content.form.layout.setting', ['%layout%' => _e(TemplateService::getComponentLabelByUid($page['layout'], TemplateService::UID_TEMPLATE_LAYOUT))]);
                $output .= '<img src="' . _e(Router::path('admin/public/images/icons/template.png')) . '" class="icon" alt="' . $iconTitle . '" title="' . $iconTitle . '">';
            }

            if (!$page['public']) {
                $iconTitle = _lang('admin.content.form.private');
                $output .= '<img src="' . _e(Router::path('admin/public/images/icons/lock3.png')) . '" class="icon" alt="' . $iconTitle . '" title="' . $iconTitle . '">';
            }

            if ($page['level'] > 0) {
                $iconTitle = _lang('admin.content.form.level') . " {$page['level']}+";

                if ($page['level_inherit']) {
                    $icon = 'lock2.png';
                    $iconTitle .= ' (' . _lang('admin.content.form.inherited') . ')';
                } else {
                    $icon = 'lock.png';
                }

                $output .= '<img src="' . _e(Router::path('admin/public/images/icons/' . $icon)) . '" class="icon" alt="' . $iconTitle . '" title="' . $iconTitle . '">';
            }

            if (!$page['visible']) {
                $iconTitle = _lang('admin.content.form.invisible');
                $output .= '<img src="' . _e(Router::path('admin/public/images/icons/eye-closed.png')) . '" class="icon" alt="' . $iconTitle . '" title="' . $iconTitle . '">';
            }
        }

        return $output;
    }
}

// static init
PageLister::init();
