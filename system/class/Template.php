<?php

namespace Sunlight;

use Sunlight\Page\PageManager;
use Sunlight\Page\PageMenu;
use Sunlight\Page\PageTreeFilter;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateService;
use Sunlight\Util\Request;
use Sunlight\Util\UrlHelper;

abstract class Template
{
    /**
     * Ziskat instanci aktualniho motivu
     *
     * @return TemplatePlugin
     */
    static function getCurrent()
    {
        // pouzit globalni promennou
        // (index)
        if (_env === Core::ENV_WEB && isset($GLOBALS['_template']) && $GLOBALS['_template'] instanceof TemplatePlugin) {
            return $GLOBALS['_template'];
        }

        // pouzit argument z GET
        // (moznost pro skripty mimo index)
        $request_template = Request::get('current_template');
        if ($request_template !== null && TemplateService::templateExists($request_template)) {
            return TemplateService::getTemplate($request_template);
        }

        // pouzit vychozi
        return TemplateService::getDefaultTemplate();
    }

    /**
     * Zmenit aktivni sablonu a layout
     *
     * @param string $idt identifikator sablony a layoutu
     * @return bool
     */
    static function change($idt)
    {
        global $_template, $_template_layout;

        $components = TemplateService::getComponentsByUid($idt, TemplateService::UID_TEMPLATE_LAYOUT);

        if ($components !== null) {
            $_template = $components['template'];
            $_template_layout = $components['layout'];

            Extend::call('tpl.switch', array(
                'template' => $_template,
                'layout' => $_template_layout,
            ));

            return true;
        }

        return false;
    }

    /**
     * Vykreslit HTML hlavicku
     */
    static function head()
    {
        global $_index, $_template;

        // pripravit css
        $css = array();
        foreach ($_template->getOption('css') as $key => $path) {
            $css[$key] = UrlHelper::isAbsolute($path) ? $path : Router::generate($path);
        }

        // pripravit js
        $js = array(
            'jquery' => Router::generate('system/js/jquery.js'),
            'sunlight' => Router::generate('system/js/sunlight.js'),
            'rangyinputs' => Router::generate('system/js/rangyinputs.js'),
        );
        foreach ($_template->getOption('js') as $key => $path) {
            $js[$key] = UrlHelper::isAbsolute($path) ? $path : Router::generate($path);
        }

        // titulek
        $title = null;
        Extend::call('tpl.title', array('title' => &$title, 'head' => true));
        if (!isset($title)) {
            if (_titletype == 1) {
                $title = _title . ' ' . _titleseparator . ' ' . $_index['title'];
            } else {
                $title = $_index['title'] . ' ' . _titleseparator . ' ' . _title;
            }
        }

        // assety
        $assets = array(
            'extend_event' => 'tpl.head',
            'css' => $css,
            'js' => $js,
            'js_before' => "\n" . Core::getJavascript(),
        );

        // sestaveni
        if (_pretty_urls) {
            echo "<base href=\"" . Core::$url . "/\">\n";
        }
        echo '<meta name="description" content="' . (isset($_index['description']) ? $_index['description'] : _description) . '">' . ((_author !== '') ? '
<meta name="author" content="' . _author . '">' : '')
            . Extend::buffer('tpl.head.meta')
            . ($_template->getOption('responsive') ? "\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">" : '')
            . GenericTemplates::renderHeadAssets($assets);

        if (_rss) {
            echo '
<link rel="alternate" type="application/rss+xml" href="' . _e(Router::rss(-1, _rss_latest_articles)) . '" title="' . _lang('rss.recentarticles') . '">';
            if (_comments) {
                echo '
<link rel="alternate" type="application/rss+xml" href="' . _e(Router::rss(-1, _rss_latest_comments)) . '" title="' . _lang('rss.recentcomments') . '">';
            }
        }

        if (_favicon) {
            echo '
<link rel="shortcut icon" href="favicon.ico?' . _cacheid . '">';
        }

        echo '
<title>' . $title . '</title>
';
    }

    /**
     * Vykreslit boxy daneho sloupce
     *
     * @param string $slot      nazev slotu
     * @param array  $overrides pretizeni konfigurace motivu
     * @return string
     */
    static function boxes($slot, array $overrides = array())
    {
        $output = '';

        if (!_notpublicsite || _logged_in) {
            global $_template, $_template_boxes;

            // nacist boxy
            if (isset($_template_boxes[$slot])) {
                $boxes = $_template_boxes[$slot];
            } else {
                $boxes = array();
            }

            // extend
            $output = Extend::buffer('tpl.boxes', array(
                'slot' => $slot,
                'boxes' => &$boxes,
                'overrides' => &$overrides,
            ));
            if ($output !== '') {
                return $output;
            }

            $options = $overrides + $_template->getOptions();

            // pocatecni tag
            if ($options['box.parent']) {
                $output .= "<{$options['box.parent']} class='boxes boxes-{$slot}'>\n";
            }
            foreach ($boxes as $item) {
                // filtrovani boxu
                if ($item['page_ids'] !== null && !PageManager::isActive(explode(',', $item['page_ids']), $item['page_children'])) {
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
     *
     * @param bool $heading  vykreslit nadpis 1/0 {@see Template::heading()}
     * @param bool $backlink vykreslit zpetny odkaz 1/0 {@see Template::backlink()}
     * @param bool $rsslink  vykreslit RSS odkaz 1/0 {@see Template::rssLink()}
     * @return string
     */
    static function content($heading = true, $backlink = true, $rsslink = true)
    {
        global $_index;

        // extend
        $output = Extend::buffer('tpl.content', array(
            'heading' => &$heading,
            'backlink' => &$backlink,
            'rsslink' => &$rsslink,
        ));

        // vychozi implementace?
        if ($output === '') {
            // rss odkaz
            if ($rsslink) {
                $output .= static::rssLink(null, false);
            }

            // nadpis
            if ($heading) {
                $output .= static::heading();
            }

            // zpetny odkaz
            if ($backlink) {
                $output .= static::backlink();
            }

            // obsah
            $output .= Extend::buffer('tpl.content.before');
            $output .= $_index['output'];
            $output .= Extend::buffer('tpl.content.after');
        }

        return $output;
    }

    /**
     * Vykreslit nadpis stranky
     *
     * @return string
     */
    static function heading()
    {
        global $_index;

        $output = '';

        if ($_index['heading_enabled']) {
            $heading = $_index[($_index['heading'] !== null) ? 'heading' : 'title'];

            // extend
            $output = Extend::buffer('tpl.heading', array('heading' => $heading));

            // vychozi implementace?
            if ($output === '') {
                $output = "<h1>{$heading}</h1>\n";
            }
        }

        return $output;
    }

    /**
     * Vykreslit zpetny odkaz
     *
     * @return string
     */
    static function backlink()
    {
        // extend
        $output = Extend::buffer('tpl.backlink');

        // vychozi implementace?
        if ($output === '' && $GLOBALS['_index']['backlink'] !== null) {
            $output = '<div class="backlink"><a href="' . _e($GLOBALS['_index']['backlink']) . '">&lt; ' . _lang('global.return') . "</a></div>\n";
        }

        return $output;
    }

    /**
     * Vykreslit RSS odkaz
     *
     * @param string|null $url    URL nebo null (= dle aktualni stranky)
     * @param bool        $inline jedna se o radkovy odkaz 1/0 (napr. v ramci nadpisu / textu)
     * @return string
     */
    static function rssLink($url = null, $inline = true)
    {
        // deaktivovane RSS / nenastavena adresa?
        if (!_rss || $url === null && $GLOBALS['_index']['rsslink'] === null) {
            return '';
        }

        // pouzit RSS adresu aktualni stranky
        if ($url === null) {
            $url = $GLOBALS['_index']['rsslink'];
        }

        // extend
        $output = Extend::buffer('tpl.rsslink', array(
            'url' => $url,
            'inline' => $inline,
        ));

        // vychozi implementace?
        if ($output === '') {
            if (!$inline) {
                $output .= '<div class="rsslink">';
            }
            $output .= '<a' . ($inline ? ' class="rsslink-inline"' : '') . ' href="' . _e($url) . '" title="' . _lang('rss.linktitle') . '"><img src="' . static::image("icons/rss.png") . "\" alt=\"rss\" class=\"icon\"></a>";
            if (!$inline) {
                $output .= "</div>\n";
            }
        }

        return $output;
    }

    /**
     * Vykreslit odkazy motivu
     *
     * @return string
     */
    static function links()
    {
        return
            "<li><a href=\"https://sunlight-cms.cz/\">SunLight CMS</a></li>\n"
            . ((!_adminlinkprivate || (_logged_in && _priv_administration)) ? '<li><a href="' . Router::generate('admin/') . '">' . _lang('global.adminlink') . "</a></li>\n" : '');
    }

    /**
     * Sestavit adresu k obrazku aktualniho motivu
     *
     * @param string $path subcesta k souboru relativne ke slozce images aktualniho motivu
     * @return string
     */
    static function image($path)
    {
        return $GLOBALS['_template']->getWebPath() . "/images/{$path}";
    }

    /**
     * Vykreslit menu
     *
     * @param int|null $ordStart    minimalni poradove cislo
     * @param int|null $ordEnd      maximalni poradove cislo
     * @param string   $cssClass    trida hlavniho tagu menu
     * @param string   $extendEvent extend udalost pro polozky menu
     * @return string
     */
    static function menu($ordStart = null, $ordEnd = null, $cssClass = null, $extendEvent = 'tpl.menu.item')
    {
        // kontrola prihlaseni v pripade neverejnych stranek
        if (!_logged_in && _notpublicsite) {
            return '';
        }

        // zjisteni aktivni stranky
        list($activeId) = PageManager::getActive();

        // nacist stranky
        $pages = PageManager::getRootPages(
            new PageTreeFilter(array(
                'ord_start' => $ordStart,
                'ord_end' => $ordEnd,
            )),
            PageMenu::getRequiredExtraColumns()
        );

        // vykreslit menu
        $output = PageMenu::render(
            $pages,
            $activeId,
            $cssClass,
            $extendEvent,
            'simple'
        );

        return $output;
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
     * filter (-)                       pole s nastavenim pro {@see PageManager::getFilter()}
     *
     * @param array $options pole s nastavenim
     * @return string
     */
    static function treeMenu(array $options)
    {
        // vychozi nastaveni
        $options += array(
            'page_id' => null,
            'children_only' => true,
            'max_depth' => null,
            'ord_start' => null,
            'ord_end' => null,
            'css_class' => null,
            'extend_event' => 'tpl.menu.item',
            'type' => 'tree',
            'filter' => array(),
        );

        // kontrola prihlaseni v pripade neverejnych stranek
        if (!_logged_in && _notpublicsite) {
            return '';
        }

        // zjisteni aktivni stranky
        list($activeId) = PageManager::getActive();

        // pouziti aktivni stranky
        if (-1 == $options['page_id']) {
            if ($activeId === null) {
                return '';
            } else {
                $options['page_id'] = $activeId;
            }
        }

        // zjistit uroven a hloubku
        try {
            list($level, $depth) = PageManager::getTreeReader()->getLevelAndDepth($options['page_id']);
            if ($options['max_depth'] !== null) {
                $depth = min($options['max_depth'], $depth);
            }
        } catch (\RuntimeException $e) {
            // stranka nenalezena
            return _debug ? _e($e->getMessage()) : '';
        }

        // nacist stranky
        $filter = new PageTreeFilter(array(
                'ord_start' => $options['ord_start'],
                'ord_end' => $options['ord_end'],
                'ord_level' => $options['page_id'] === null ? $level : $level + 1,
            ) + $options['filter']);

        $pages = PageManager::getFlatTree(
            $options['page_id'],
            $depth,
            $filter,
            PageMenu::getRequiredExtraColumns()
        );
        if ($options['children_only']) {
            $pages = PageManager::getTreeReader()->extractChildren($pages, $options['page_id'], true);
        }

        // vykreslit menu
        $output = PageMenu::render(
            $pages,
            $activeId,
            $options['css_class'],
            $options['extend_event'],
            $options['type']
        );

        return $output;
    }

    /**
     * Vykreslit drobeckovou navigaci
     *
     * @param array $breadcrumbs vychozi drobecky
     * @param bool $onlyWhenMultiple vykreslit pouze 2 a vice drobecku
     * @return string
     */
    static function breadcrumbs($breadcrumbs = array(), $onlyWhenMultiple = false)
    {
        global $_index;

        // zjistit aktivni stranku a jeji uroven
        list($pageId, $pageData) = PageManager::getActive();
        if ($pageData !== null) {
            $rootLevel = $pageData['node_level'];
        } else {
            $rootLevel = null;
        }

        // pridat stranky
        if ($pageId !== null) {
            foreach (PageManager::getPath($pageId, $rootLevel) as $page) {
                $breadcrumbs[] = array(
                    'title' => $page['title'],
                    'url' => Router::page($page['id'], $page['slug']),
                );
            }
        }

        // pridat modul
        if (self::currentIsModule()) {
            $breadcrumbs[] = array(
                'title' => $_index['title'],
                'url' => $_index['url'],
            );
        }

        // pridat drobecky aktualni stranky
        foreach ($_index['crumbs'] as $crumb) {
            $breadcrumbs[] = $crumb;
        }

        // extend udalost
        $output = '';
        Extend::call('tpl.breadcrumbs', array(
            'breadcrumbs' => &$breadcrumbs,
            'output' => &$output,
        ));

        // vykreslit
        if (!empty($breadcrumbs) && (!$onlyWhenMultiple || count($breadcrumbs) >= 2) && $output === '') {
            $output .= "<ul class=\"breadcrumbs\">\n";
            foreach ($breadcrumbs as $crumb) {
                $output .= "<li><a href=\"" . _e($crumb['url']) . "\">{$crumb['title']}</a></li>\n";
            }
            $output .= "</ul>\n";
        }

        return $output;
    }

    /**
     * Vykreslit titulek aktualni stranky
     *
     * @return string
     */
    static function title()
    {
        // overload pluginem
        $title = null;
        Extend::call('tpl.title', array('title' => &$title, 'head' => false));
        if (!isset($title)) {
            $title = $GLOBALS['_index']['title'];
        }

        return $title;
    }

    /**
     * Ziskat titulek stranek
     *
     * @return string
     */
    static function siteTitle()
    {
        return _title;
    }

    /**
     * Ziskat popis stranek
     *
     * @return string
     */
    static function siteDescription()
    {
        return _description;
    }

    /**
     * Ziskat zakladni adresu stranek
     */
    static function siteUrl()
    {
        return Core::$url . '/';
    }

    /**
     * Vykreslit uzivatelske menu
     *
     * @param bool $profileLink vykreslit odkaz na profil 1/0
     * @return string
     */
    static function userMenu($profileLink = true, $adminLink = true)
    {
        // pripravit polozky
        $items = array();

        if (!_logged_in) {
            // prihlaseni
            $items['login'] = array(
                Router::module('login', 'login_form_return=' . rawurlencode($_SERVER['REQUEST_URI'])),
                _lang('usermenu.login'),
            );
            if (_registration) {
                // registrace
                $items['reg'] = array(
                    Router::module('reg'),
                    _lang('usermenu.registration'),
                );
            }
        } else {
            // profil
            if ($profileLink) {
                $items['profile'] = array(
                    Router::module('profile', 'id=' . _user_name),
                    _lang('usermenu.profile'),
                );
            }

            // vzkazy
            if (_messages) {
                $messages_count = User::getUnreadPmCount();
                if ($messages_count != 0) {
                    $messages_count = " [{$messages_count}]";
                } else {
                    $messages_count = '';
                }
                $items['messages'] = array(
                    Router::module('messages'),
                    _lang('usermenu.messages') . $messages_count,
                );
            }

            // nastaveni
            $items['settings'] = array(
                Router::module('settings'),
                _lang('usermenu.settings'),
            );

            // administrace
            if ($adminLink && _priv_administration) {
                $items['admin'] = array(
                    Router::generate('admin/'),
                    _lang('global.adminlink')
                );
            }
        }

        if (_ulist && (!_notpublicsite || _logged_in)) {
            // seznam uzivatelu
            $items['ulist'] = array(
                Router::module('ulist'),
                _lang('usermenu.ulist'),
            );
        }

        // odhlaseni
        if (_logged_in) {
            $items['logout'] = array(
                Xsrf::addToUrl(Router::generate("system/script/logout.php?_return=" . rawurlencode($_SERVER['REQUEST_URI']))),
                _lang('usermenu.logout'),
            );
        }

        // vykreslit
        $output = Extend::buffer('tpl.usermenu', array('items' => &$items));
        if ($output === '' && !empty($items)) {
            $output = "<ul class=\"user-menu " . (_logged_in ? 'logged-in' : 'not-logged-in') . "\">\n";
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
     *
     * @return int|null
     */
    static function currentID()
    {
        return $GLOBALS['_index']['id'];
    }

    /**
     * Zjistit, zda je aktualni obsah typu "stranka"
     *
     * @return bool
     */
    static function currentIsPage()
    {
        return $GLOBALS['_index']['is_page'] && $GLOBALS['_index']['is_successful'];
    }

    /**
     * Zjistit, zda je aktualni obsah typu "clanek" (v kategorii)
     *
     * @return bool
     */
    static function currentIsArticle()
    {
        return
            $GLOBALS['_index']['is_page']
            && $GLOBALS['_index']['is_successful']
            && $GLOBALS['_page']['type'] == _page_category
            && $GLOBALS['_index']['segment'] !== null;
    }

    /**
     * Zjistit, zda je aktualni obsah typu "tema" (ve foru)
     *
     * @return bool
     */
    static function currentIsTopic()
    {
        return
            $GLOBALS['_index']['is_page']
            && $GLOBALS['_index']['is_successful']
            && $GLOBALS['_page']['type'] == _page_forum
            && $GLOBALS['_index']['segment'] !== null;
    }

    /**
     * Zjistit, zda je aktualni obsah typu "modul"
     *
     * @return bool
     */
    static function currentIsModule()
    {
        return $GLOBALS['_index']['is_module'] && $GLOBALS['_index']['is_successful'];
    }

    /**
     * Zjisteni, zda je aktualni obsah hlavni strana
     *
     * @return bool
     */
    static function currentIsIndex()
    {
        return static::currentIsPage() && $GLOBALS['_index']['id'] == _index_page_id;
    }
}
