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
     * Ziskat instanci aktualniho motivu
     */
    static function getCurrent(): TemplatePlugin
    {
        if (Core::$env === Core::ENV_WEB) {
            return $GLOBALS['_index']->template;
        }

        return TemplateService::getDefaultTemplate();
    }

    /**
     * Vykreslit HTML hlavicku
     */
    static function head(): string
    {
        global $_index;

        $output = '';

        // pripravit css
        $css = [];
        foreach ($_index->template->getOption('css') as $key => $path) {
            $css[$key] = UrlHelper::isAbsolute($path) ? $path : Router::path($path);
        }

        // pripravit js
        $js = [
            'jquery' => Router::path('system/js/jquery.js'),
            'sunlight' => Router::path('system/js/sunlight.js'),
            'rangyinputs' => Router::path('system/js/rangyinputs.js'),
        ];
        foreach ($_index->template->getOption('js') as $key => $path) {
            $js[$key] = UrlHelper::isAbsolute($path) ? $path : Router::path($path);
        }

        // titulek
        $title = Extend::buffer('tpl.title', ['head' => true]);
        if ($title === '') {
            if (Settings::get('titletype') == 1) {
                $title = Settings::get('title') . ' ' . Settings::get('titleseparator') . ' ' . $_index->title;
            } else {
                $title = $_index->title . ' ' . Settings::get('titleseparator') . ' ' . Settings::get('title');
            }
        }

        // assety
        $assets = [
            'extend_event' => 'tpl.head',
            'css' => $css,
            'js' => $js,
            'js_before' => "\n" . Core::getJavascript(),
        ];

        // sestaveni
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
     * Vykreslit boxy daneho sloupce
     *
     * @param string $slot nazev slotu
     * @param array $overrides pretizeni konfigurace motivu
     */
    static function boxes(string $slot, array $overrides = []): string
    {
        $output = '';

        if (!Settings::get('notpublicsite') || User::isLoggedIn()) {
            global $_index;

            // ziskat boxy
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

            // pocatecni tag
            if ($options['box.parent']) {
                $output .= "<{$options['box.parent']} class='boxes boxes-{$slot}'>\n";
            }
            foreach ($boxes as $item) {
                // filtrovani boxu
                if ($item['page_ids'] !== null && !Page::isActive(explode(',', $item['page_ids']), $item['page_children'])) {
                    continue;
                }

                // kod titulku
                if ($item['title'] !== '') {
                    $title = "<{$options['box.title']} class='box-title'>{$item['title']}</{$options['box.title']}>\n";
                } else {
                    $title = '';
                }

                // titulek venku
                if (!$options['box.title.inside']) {
                    $output .= $title;
                }

                // starttag polozky
                if ($options['box.item']) {
                    $output .= "<{$options['box.item']} class='box-item" . (isset($item['class']) ? ' ' . _e($item['class']) : '') . "'>\n";
                }

                // titulek vevnitr
                if ($options['box.title.inside']) {
                    $output .= $title;
                }

                // obsah
                $output .= Hcm::parse($item['content']);

                // endtag polozky
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
     * Vykreslit obsah
     */
    static function content(): string
    {
        global $_index;

        Extend::call('tpl.content', ['content' => &$_index->output]);

        return $_index->output;
    }

    /**
     * Vykreslit nadpis stranky
     */
    static function heading(): string
    {
        global $_index;

        $output = '';

        if ($_index->headingEnabled) {
            $heading = $_index->heading ?? $_index->title;

            // extend
            $output = Extend::buffer('tpl.heading', ['heading' => &$heading]);

            // vychozi implementace
            if ($output === '') {
                $output = "<h1>{$heading}</h1>\n";
            }
        }

        return $output;
    }

    /**
     * Vykreslit zpetny odkaz
     */
    static function backlink(): string
    {
        global $_index;

        // extend
        $output = Extend::buffer('tpl.backlink', ['backlink' => &$_index->backlink]);

        // vychozi implementace
        if ($output === '' && $_index->backlink !== null) {
            $output = '<div class="backlink"><a href="' . _e($_index->backlink) . '">&lt; ' . _lang('global.return') . "</a></div>\n";
        }

        return $output;
    }

    /**
     * Vykreslit odkazy motivu
     */
    static function links(): string
    {
        return
            "<li><a href=\"https://sunlight-cms.cz/\">SunLight CMS</a></li>\n"
            . ((!Settings::get('adminlinkprivate') || (User::isLoggedIn() && User::hasPrivilege('administration'))) ? '<li><a href="' . _e(Router::adminIndex()) . '">' . _lang('global.adminlink') . "</a></li>\n" : '');
    }

    /**
     * Sestavit adresu k obrazku aktualniho motivu
     *
     * @param string $name subcesta k souboru relativne ke slozce images aktualniho motivu
     */
    static function image(string $name): string
    {
        return self::getCurrent()->getImagePath($name);
    }

    /**
     * Vykreslit menu
     *
     * @param int|null $ordStart minimalni poradove cislo
     * @param int|null $ordEnd maximalni poradove cislo
     * @param string|null $cssClass trida hlavniho tagu menu
     * @param string $extendEvent extend udalost pro polozky menu
     */
    static function menu(?int $ordStart = null, ?int $ordEnd = null, ?string $cssClass = null, string $extendEvent = 'tpl.menu.item'): string
    {
        // kontrola prihlaseni v pripade neverejnych stranek
        if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
            return '';
        }

        // zjisteni aktivni stranky
        [$activeId] = Page::getActive();

        // nacist stranky
        $pages = Page::getRootPages(
            new PageTreeFilter([
                'ord_start' => $ordStart,
                'ord_end' => $ordEnd,
            ]),
            PageMenu::getRequiredExtraColumns()
        );

        // vykreslit menu
        return PageMenu::render(
            $pages,
            $activeId,
            $cssClass,
            $extendEvent,
            'simple'
        );
    }

    /**
     * Vykreslit stromove menu
     *
     * Mozne klice v $options:
     * ---------------------------------------------------------------------------------
     * page_id (-)                      ID referencni stranky (-1 = aktivni stranka)
     * children_only (1)                vypsat pouze potomky, je-li uvedeno page_id
     * max_depth (-)                    maximalni vypsana hloubka (null = neomezeno, 0+)
     * ord_start (-)                    limit poradi od
     * ord_end (-)                      limit poradi do
     * css_class (-)                    trida hlavniho tagu menu
     * extend_event ("tpl.menu.item")   extend udalost pro polozky menu
     * type ("tree")                    identifikator typu menu
     * filter (-)                       pole s nastavenim pro {@see Page::getFilter()}
     *
     * @param array $options pole s nastavenim
     */
    static function treeMenu(array $options): string
    {
        // vychozi nastaveni
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

        // kontrola prihlaseni v pripade neverejnych stranek
        if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
            return '';
        }

        // zjisteni aktivni stranky
        [$activeId] = Page::getActive();

        // pouziti aktivni stranky
        if ($options['page_id'] == -1) {
            if ($activeId === null) {
                return '';
            }

            $options['page_id'] = $activeId;
        }

        // zjistit uroven a hloubku
        try {
            [$level, $depth] = Page::getTreeReader()->getLevelAndDepth($options['page_id']);
            if ($options['max_depth'] !== null) {
                $depth = min($options['max_depth'], $depth);
            }
        } catch (\RuntimeException $e) {
            // stranka nenalezena
            return Core::$debug ? _e($e->getMessage()) : '';
        }

        // nacist stranky
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

        // vykreslit menu
        return PageMenu::render(
            $pages,
            $activeId,
            $options['css_class'],
            $options['extend_event'],
            $options['type']
        );
    }

    /**
     * Vykreslit drobeckovou navigaci
     *
     * @param array $breadcrumbs vychozi drobecky
     * @param bool $onlyWhenMultiple vykreslit pouze 2 a vice drobecku
     */
    static function breadcrumbs(array $breadcrumbs = [], bool $onlyWhenMultiple = false): string
    {
        global $_index;

        // zjistit aktivni stranku a jeji uroven
        [$pageId, $pageData] = Page::getActive();
        if ($pageData !== null) {
            $rootLevel = $pageData['node_level'];
        } else {
            $rootLevel = null;
        }

        // pridat stranky
        if ($pageId !== null) {
            foreach (Page::getPath($pageId, $rootLevel) as $page) {
                $breadcrumbs[] = [
                    'title' => $page['title'],
                    'url' => Router::page($page['id'], $page['slug']),
                ];
            }
        }

        // pridat drobecky aktualni stranky
        foreach ($_index->crumbs as $crumb) {
            $breadcrumbs[] = $crumb;
        }

        // extend udalost
        $output = '';
        Extend::call('tpl.breadcrumbs', [
            'breadcrumbs' => &$breadcrumbs,
            'only_when_multiple' => $onlyWhenMultiple,
            'output' => &$output,
        ]);

        // vykreslit
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
     * Vykreslit titulek aktualni stranky
     */
    static function title(): string
    {
        $title = Extend::buffer('tpl.title', ['head' => false]);

        return $title !== '' ? $title : $GLOBALS['_index']->title;
    }

    /**
     * Ziskat titulek stranek
     */
    static function siteTitle(): string
    {
        return Settings::get('title');
    }

    /**
     * Ziskat popis stranek
     */
    static function siteDescription(): string
    {
        return Settings::get('description');
    }

    /**
     * Ziskat zakladni adresu stranek
     */
    static function siteUrl(): string
    {
        return Core::getBaseUrl()->build() . '/';
    }

    /**
     * Ziskat zakladni cestu stranek
     */
    static function sitePath(): string
    {
        return Core::getBaseUrl()->getPath() . '/';
    }

    /**
     * Vykreslit uzivatelske menu
     *
     * @param bool $profileLink vykreslit odkaz na profil 1/0
     */
    static function userMenu(bool $profileLink = true, bool $adminLink = true): string
    {
        // pripravit polozky
        $items = [];

        if (!User::isLoggedIn()) {
            // prihlaseni
            $items['login'] = [
                Router::module('login', ['query' => ['login_form_return' => $_SERVER['REQUEST_URI']]]),
                _lang('usermenu.login'),
            ];
            if (Settings::get('registration')) {
                // registrace
                $items['reg'] = [
                    Router::module('reg'),
                    _lang('usermenu.registration'),
                ];
            }
        } else {
            // profil
            if ($profileLink) {
                $items['profile'] = [
                    Router::module('profile', ['query' => ['id' => User::getUsername()]]),
                    _lang('usermenu.profile'),
                ];
            }

            // vzkazy
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

            // nastaveni
            $items['settings'] = [
                Router::module('settings'),
                _lang('usermenu.settings'),
            ];

            // administrace
            if ($adminLink && User::hasPrivilege('administration')) {
                $items['admin'] = [
                    Router::adminIndex(),
                    _lang('global.adminlink')
                ];
            }
        }

        if (Settings::get('ulist') && (!Settings::get('notpublicsite') || User::isLoggedIn())) {
            // seznam uzivatelu
            $items['ulist'] = [
                Router::module('ulist'),
                _lang('usermenu.ulist'),
            ];
        }

        // odhlaseni
        if (User::isLoggedIn()) {
            $items['logout'] = [
                Xsrf::addToUrl(Router::path('system/script/logout.php', ['query' => ['_return' => $_SERVER['REQUEST_URI']]])),
                _lang('usermenu.logout'),
            ];
        }

        // vykreslit
        $output = Extend::buffer('tpl.usermenu', ['items' => &$items]);
        if ($output === '' && !empty($items)) {
            $output = '<ul class="user-menu ' . (User::isLoggedIn() ? 'logged-in' : 'not-logged-in') . "\">\n";
            $output .= Extend::buffer('tpl.usermenu.start');
            foreach ($items as $id => $item) {
                $output .= "<li class=\"user-menu-{$id}\"><a href=\"" . _e($item[0]) . "\">{$item[1]}</a></li>\n";
            }
            $output .= Extend::buffer('tpl.usermenu.end');
            $output .= "</ul>\n";
        }

        return $output;
    }

    /**
     * Zjistit ID aktualni stranky
     */
    static function currentID(): ?int
    {
        return $GLOBALS['_index']->id;
    }

    /**
     * Zjistit, zda je aktualni obsah typu "stranka"
     */
    static function currentIsPage(): bool
    {
        return $GLOBALS['_index']->type === WebState::PAGE;
    }

    /**
     * Zjistit, zda je aktualni obsah typu "clanek" (v kategorii)
     */
    static function currentIsArticle(): bool
    {
        return
            $GLOBALS['_index']->type === WebState::PAGE
            && $GLOBALS['_page']['type'] == Page::CATEGORY
            && $GLOBALS['_index']->segment !== null;
    }

    /**
     * Zjistit, zda je aktualni obsah typu "tema" (ve foru)
     */
    static function currentIsTopic(): bool
    {
        return
            $GLOBALS['_index']->type === WebState::PAGE
            && $GLOBALS['_page']['type'] == Page::FORUM
            && $GLOBALS['_index']->segment !== null;
    }

    /**
     * Zjistit, zda je aktualni obsah typu "modul"
     */
    static function currentIsModule(): bool
    {
        return $GLOBALS['_index']->type === WebState::MODULE;
    }

    /**
     * Zjisteni, zda je aktualni obsah hlavni strana
     */
    static function currentIsIndex(): bool
    {
        return self::currentIsPage() && $GLOBALS['_index']->id == Settings::get('index_page_id');
    }
}
