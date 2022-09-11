<?php

namespace Sunlight;

use Sunlight\Page\Page;
use Sunlight\Page\PageMenu;
use Sunlight\Page\PageTreeFilter;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateService;
use Sunlight\Util\UrlHelper;

abstract class Template
{
    /**
     * Get currently active template
     */
    static function getCurrent(): TemplatePlugin
    {
        if (Core::$env === Core::ENV_WEB) {
            return $GLOBALS['_index']->template;
        }

        return TemplateService::getDefaultTemplate();
    }

    /**
     * Render <head> contents
     */
    static function head(): string
    {
        global $_index;

        $output = '';

        // CSS
        $css = [];

        foreach ($_index->template->getOption('css') as $key => $path) {
            $css[$key] = UrlHelper::isAbsolute($path) ? $path : Router::path($path);
        }

        // JS
        $js = [
            'jquery' => Router::path('system/js/jquery.js'),
            'sunlight' => Router::path('system/js/sunlight.js'),
            'rangyinputs' => Router::path('system/js/rangyinputs.js'),
        ];

        foreach ($_index->template->getOption('js') as $key => $path) {
            $js[$key] = UrlHelper::isAbsolute($path) ? $path : Router::path($path);
        }

        // title
        $title = Extend::buffer('tpl.title', ['head' => true]);

        if ($title === '') {
            if (Settings::get('titletype') == 1) {
                $title = Settings::get('title') . ' ' . Settings::get('titleseparator') . ' ' . $_index->title;
            } else {
                $title = $_index->title . ' ' . Settings::get('titleseparator') . ' ' . Settings::get('title');
            }
        }

        // assets
        $assets = [
            'extend_event' => 'tpl.head',
            'css' => $css,
            'js' => $js,
            'js_before' => "\n" . Core::getJavascript(),
        ];

        // render
        $output .= '<meta name="description" content="' . ($_index->description ?? Settings::get('description')) . '">' . ((Settings::get('author') !== '') ? '
<meta name="author" content="' . Settings::get('author') . '">' : '')
            . Extend::buffer('tpl.head.meta')
            . ($_index->template->getOption('responsive') ? "\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">" : '')
            . GenericTemplates::renderHeadAssets($assets);

        if (Settings::get('favicon')) {
            $output .= '
<link rel="shortcut icon" href="' . _e(Router::path('favicon.ico') . '?' . Settings::get('cacheid')) . '">';
        }

        $output .= '
<title>' . $title . '</title>
';

        return $output;
    }

    /**
     * Render boxes
     *
     * @param string $slot slot name
     * @param array $overrides template option overrides
     */
    static function boxes(string $slot, array $overrides = []): string
    {
        $output = '';

        if (!Settings::get('notpublicsite') || User::isLoggedIn()) {
            global $_index;

            // get boxes
            $boxes = $_index->templateBoxes[$slot] ?? [];

            // extend
            $output = Extend::buffer('tpl.boxes', [
                'slot' => $slot,
                'boxes' => &$boxes,
                'overrides' => &$overrides,
            ]);

            if ($output !== '') {
                return $output;
            }

            $options = $overrides + $_index->template->getOptions();

            // opening tag
            if ($options['box.parent']) {
                $output .= "<{$options['box.parent']} class=\"boxes boxes-{$slot}\">\n";
            }

            foreach ($boxes as $item) {
                // filter boxes
                if ($item['page_ids'] !== null && !Page::isActive(explode(',', $item['page_ids']), $item['page_children'])) {
                    continue;
                }

                // prepare title
                if ($item['title'] !== '') {
                    $title = "<{$options['box.title']} class=\"box-title\">{$item['title']}</{$options['box.title']}>\n";
                } else {
                    $title = '';
                }

                // title (outside)
                if (!$options['box.title.inside']) {
                    $output .= $title;
                }

                // opening tag
                if ($options['box.item']) {
                    $output .= "<{$options['box.item']} class=\"box-item" . (isset($item['class']) ? ' ' . _e($item['class']) : '') . "\">\n";
                }

                // title (inside)
                if ($options['box.title.inside']) {
                    $output .= $title;
                }

                // content
                $output .= Hcm::parse($item['content']);

                // closing tag
                if ($options['box.item']) {
                    $output .= "\n</{$options['box.item']}>";
                }
            }

            if ($options['box.parent']) {
                $output .= "</{$options['box.parent']}>\n";
            }
        }

        return $output;
    }

    /**
     * Render content
     */
    static function content(): string
    {
        global $_index;

        Extend::call('tpl.content', ['content' => &$_index->output]);

        return $_index->output;
    }

    /**
     * Render heading
     */
    static function heading(): string
    {
        global $_index;

        $output = '';

        if ($_index->headingEnabled) {
            $heading = $_index->heading ?? $_index->title;

            // extend
            $output = Extend::buffer('tpl.heading', ['heading' => &$heading]);

            // default implementation
            if ($output === '') {
                $output = "<h1>{$heading}</h1>\n";
            }
        }

        return $output;
    }

    /**
     * Render backlick
     */
    static function backlink(): string
    {
        global $_index;

        // extend
        $output = Extend::buffer('tpl.backlink', ['backlink' => &$_index->backlink]);

        // default implementation
        if ($output === '' && $_index->backlink !== null) {
            $output = '<div class="backlink"><a href="' . _e($_index->backlink) . '">&lt; ' . _lang('global.return') . "</a></div>\n";
        }

        return $output;
    }

    /**
     * Render template links
     */
    static function links(): string
    {
        return
            "<li><a href=\"https://sunlight-cms.cz/\">SunLight CMS</a></li>\n"
            . ((!Settings::get('adminlinkprivate') || (User::isLoggedIn() && User::hasPrivilege('administration'))) ? '<li><a href="' . _e(Router::adminIndex()) . '">' . _lang('global.adminlink') . "</a></li>\n" : '');
    }

    /**
     * Compose path to a current template's image
     *
     * @param string $name subpath in the "images" directory
     */
    static function image(string $name): string
    {
        return self::getCurrent()->getImagePath($name);
    }

    /**
     * Render menu
     *
     * @param int|null $ordStart min. order number
     * @param int|null $ordEnd max order number
     * @param string|null $cssClass custom CSS class for the container
     * @param string $extendEvent extend event for menu items
     */
    static function menu(?int $ordStart = null, ?int $ordEnd = null, ?string $cssClass = null, string $extendEvent = 'tpl.menu.item'): string
    {
        // check login if site is not public
        if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
            return '';
        }

        // determine active page
        [$activeId] = Page::getActive();

        // load pages
        $pages = Page::getRootPages(
            new PageTreeFilter([
                'ord_start' => $ordStart,
                'ord_end' => $ordEnd,
            ]),
            PageMenu::getRequiredExtraColumns()
        );

        // render menu
        return PageMenu::render(
            $pages,
            $activeId,
            $cssClass,
            $extendEvent,
            'simple'
        );
    }

    /**
     * Render tree menu
     *
     * Supported keys in $options:
     * ---------------------------------------------------------------------------------
     * page_id (-)                      ID of page to render menu for (-1 = current)
     * children_only (1)                only list children (requires page_id)
     * max_depth (-)                    maximum number of levels (null = unlimited)
     * ord_start (-)                    only list pages with this order number or higher
     * ord_end (-)                      only list pages with this order number or less
     * css_class (-)                    CSS class for the container tag
     * extend_event ("tpl.menu.item")   menu item extend event name
     * type ("tree")                    menu type identifier (for events)
     * filter (-)                       additional options for {@see PageTreeFilter}
     */
    static function treeMenu(array $options): string
    {
        $options += [
            'page_id' => null,
            'children_only' => true,
            'max_depth' => null,
            'ord_start' => null,
            'ord_end' => null,
            'css_class' => null,
            'extend_event' => 'tpl.menu.item',
            'type' => 'tree',
            'filter' => [],
        ];

        // check login if site is not public
        if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
            return '';
        }

        // get active page
        [$activeId] = Page::getActive();

        // use active page
        if ($options['page_id'] == -1) {
            if ($activeId === null) {
                return '';
            }

            $options['page_id'] = $activeId;
        }

        // determine page tree level and depth
        try {
            [$level, $depth] = Page::getTreeReader()->getLevelAndDepth($options['page_id']);

            if ($options['max_depth'] !== null) {
                $depth = min($options['max_depth'], $depth);
            }
        } catch (\RuntimeException $e) {
            // page not found
            return Core::$debug ? _e($e->getMessage()) : '';
        }

        // load pages
        $filter = new PageTreeFilter([
                'ord_start' => $options['ord_start'],
                'ord_end' => $options['ord_end'],
                'ord_level' => $options['page_id'] === null ? $level : $level + 1,
            ] + $options['filter']);

        $pages = Page::getFlatTree(
            $options['page_id'],
            $depth,
            $filter,
            PageMenu::getRequiredExtraColumns()
        );

        if ($options['children_only']) {
            $pages = Page::getTreeReader()->extractChildren($pages, $options['page_id'], true);
        }

        // render menu
        return PageMenu::render(
            $pages,
            $activeId,
            $options['css_class'],
            $options['extend_event'],
            $options['type']
        );
    }

    /**
     * Render breadcrumbs
     *
     * @param array $breadcrumbs default breadcrumbs to add to
     * @param bool $onlyWhenMultiple only render if there are 2 or more crumbs
     */
    static function breadcrumbs(array $breadcrumbs = [], bool $onlyWhenMultiple = false): string
    {
        global $_index;

        // determine active page and its level
        [$pageId, $pageData] = Page::getActive();

        if ($pageData !== null) {
            $rootLevel = $pageData['node_level'];
        } else {
            $rootLevel = null;
        }

        // add pages
        if ($pageId !== null) {
            foreach (Page::getPath($pageId, $rootLevel) as $page) {
                $breadcrumbs[] = [
                    'title' => $page['title'],
                    'url' => Router::page($page['id'], $page['slug']),
                ];
            }
        }

        // add current page's crumbs
        foreach ($_index->crumbs as $crumb) {
            $breadcrumbs[] = $crumb;
        }

        // extend
        $output = '';
        Extend::call('tpl.breadcrumbs', [
            'breadcrumbs' => &$breadcrumbs,
            'only_when_multiple' => $onlyWhenMultiple,
            'output' => &$output,
        ]);

        // render
        if (!empty($breadcrumbs) && (!$onlyWhenMultiple || count($breadcrumbs) >= 2) && $output === '') {
            $output .= "<ul class=\"breadcrumbs\">\n";

            foreach ($breadcrumbs as $crumb) {
                $output .= '<li><a href="' . _e($crumb['url']) . "\">{$crumb['title']}</a></li>\n";
            }

            $output .= "</ul>\n";
        }

        return $output;
    }

    /**
     * Render current page's title
     */
    static function title(): string
    {
        $title = Extend::buffer('tpl.title', ['head' => false]);

        return $title !== '' ? $title : $GLOBALS['_index']->title;
    }

    /**
     * Render the site title
     */
    static function siteTitle(): string
    {
        return Settings::get('title');
    }

    /**
     * Render the site description
     */
    static function siteDescription(): string
    {
        return Settings::get('description');
    }

    /**
     * Render the site's base URL
     */
    static function siteUrl(): string
    {
        return Core::getBaseUrl()->build() . '/';
    }

    /**
     * Render the site's base path
     */
    static function sitePath(): string
    {
        return Core::getBaseUrl()->getPath() . '/';
    }

    /**
     * Render user menu
     */
    static function userMenu(bool $profileLink = true, bool $adminLink = true): string
    {
        $items = [];

        if (!User::isLoggedIn()) {
            // login
            $items['login'] = [
                Router::module('login', ['query' => ['login_form_return' => $_SERVER['REQUEST_URI']]]),
                _lang('usermenu.login'),
            ];

            // registration
            if (Settings::get('registration')) {
                $items['reg'] = [
                    Router::module('reg'),
                    _lang('usermenu.registration'),
                ];
            }
        } else {
            // profile
            if ($profileLink) {
                $items['profile'] = [
                    Router::module('profile', ['query' => ['id' => User::getUsername()]]),
                    _lang('usermenu.profile'),
                ];
            }

            // messages
            if (Settings::get('messages')) {
                $messages_count = User::getUnreadPmCount();

                if ($messages_count != 0) {
                    $messages_count = " [{$messages_count}]";
                } else {
                    $messages_count = '';
                }

                $items['messages'] = [
                    Router::module('messages'),
                    _lang('usermenu.messages') . $messages_count,
                ];
            }

            // settings
            $items['settings'] = [
                Router::module('settings'),
                _lang('usermenu.settings'),
            ];

            // admin
            if ($adminLink && User::hasPrivilege('administration')) {
                $items['admin'] = [
                    Router::adminIndex(),
                    _lang('global.adminlink')
                ];
            }
        }

        if (Settings::get('ulist') && (!Settings::get('notpublicsite') || User::isLoggedIn())) {
            // user list
            $items['ulist'] = [
                Router::module('ulist'),
                _lang('usermenu.ulist'),
            ];
        }

        // logout
        if (User::isLoggedIn()) {
            $items['logout'] = [
                Xsrf::addToUrl(Router::path('system/script/logout.php', ['query' => ['_return' => $_SERVER['REQUEST_URI']]])),
                _lang('usermenu.logout'),
            ];
        }

        // render
        $output = Extend::buffer('tpl.usermenu', ['items' => &$items]);

        if ($output === '' && !empty($items)) {
            $output = '<ul class="user-menu ' . (User::isLoggedIn() ? 'logged-in' : 'not-logged-in') . "\">\n";
            $output .= Extend::buffer('tpl.usermenu.start');

            foreach ($items as $id => $item) {
                $output .= '<li class="user-menu-' . $id . '"><a href="' . _e($item[0]) . '">' . $item[1] . "</a></li>\n";
            }

            $output .= Extend::buffer('tpl.usermenu.end');
            $output .= "</ul>\n";
        }

        return $output;
    }

    /**
     * Get ID of current page
     */
    static function currentID(): ?int
    {
        return $GLOBALS['_index']->id;
    }

    /**
     * See if a page is being rendered
     */
    static function currentIsPage(): bool
    {
        return $GLOBALS['_index']->type === WebState::PAGE;
    }

    /**
     * See if an article is being rendered
     */
    static function currentIsArticle(): bool
    {
        return
            $GLOBALS['_index']->type === WebState::PAGE
            && $GLOBALS['_page']['type'] == Page::CATEGORY
            && $GLOBALS['_index']->segment !== null;
    }

    /**
     * See if a forum topic is being rendered
     */
    static function currentIsTopic(): bool
    {
        return
            $GLOBALS['_index']->type === WebState::PAGE
            && $GLOBALS['_page']['type'] == Page::FORUM
            && $GLOBALS['_index']->segment !== null;
    }

    /**
     * See if a module is being rendered
     */
    static function currentIsModule(): bool
    {
        return $GLOBALS['_index']->type === WebState::MODULE;
    }

    /**
     * See if the index page is being rendered
     */
    static function currentIsIndex(): bool
    {
        return self::currentIsPage() && $GLOBALS['_index']->id == Settings::get('index_page_id');
    }
}
