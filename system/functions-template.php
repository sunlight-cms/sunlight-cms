<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Page\PageManager;
use Sunlight\Page\PageMenu;
use Sunlight\Page\PageTreeFilter;
use Sunlight\Plugin\TemplateService;

/**
 * Zmenit aktivni sablonu a layout
 *
 * @param string $idt identifikator sablony a layoutu
 * @return bool
 */
function _templateSwitch($idt)
{
    global $_template, $_template_layout;

    $components = TemplateService::getComponentsByUid($idt, TemplateService::UID_TEMPLATE_LAYOUT);

    if (null !== $components) {
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
function _templateHead()
{
    global $_index, $_template;

    // pripravit css
    $css = array();
    foreach ($_template->getOption('css') as $key => $path) {
        $css[$key] = _link($path);
    }

    // pripravit js
    $js = array(
        'jquery' => _link('system/js/jquery.js'),
        'sunlight' => _link('system/js/sunlight.js'),
        'rangyinputs' => _link('system/js/rangyinputs.js'),
    );
    foreach ($_template->getOption('js') as $key => $path) {
        $js[$key] = _link($path);
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
    echo '<meta name="keywords" content="' . (isset($_index['keywords']) ? $_index['keywords'] : _keywords) . '">
<meta name="description" content="' . (isset($_index['description']) ? $_index['description'] : _description) . '">' . ((_author !== '') ? '
<meta name="author" content="' . _author . '">' : '') . '
<meta name="robots" content="index, follow">'
 . Extend::buffer('tpl.head.meta')
 . _headAssets($assets);

    if (_rss) {
        echo '
<link rel="alternate" type="application/rss+xml" href="' . _linkRSS(-1, _rss_latest_articles) . '" title="' . $GLOBALS['_lang']['rss.recentarticles'] . '">';
        if (_comments) {
            echo '
<link rel="alternate" type="application/rss+xml" href="' . _linkRSS(-1, _rss_latest_comments) . '" title="' . $GLOBALS['_lang']['rss.recentcomments'] . '">';
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
function _templateBoxes($slot, array $overrides = array())
{
    $output = '';

    if (!_notpublicsite || _login) {
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
        if ('' !== $output) {
            return $output;
        }

        $options = $overrides + $_template->getOptions();

        // pocatecni tag
        if ($options['box.parent']) {
            $output .= "<{$options['box.parent']} class='boxes boxes-{$slot}'>\n";
        }
        foreach ($boxes as $item) {
            // filtrovani boxu
            if (null !== $item['page_ids'] && !PageManager::isActive(explode(',', $item['page_ids']), $item['page_children'])) {
                continue;
            }

            // kod titulku
            if ('' !== $item['title']) {
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
            $output .= _parseHCM($item['content']);

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
 * @param bool $heading  vykreslit nadpis 1/0 {@see _templateHeading()}
 * @param bool $backlink vykreslit zpetny odkaz 1/0 {@see _templateBacklink()}
 * @param bool $rsslink  vykreslit RSS odkaz 1/0 {@see _templateRsslink()}
 * @return string
 */
function _templateContent($heading = true, $backlink = true, $rsslink = true)
{
    global $_index;

    // extend
    $output = Extend::buffer('tpl.content', array(
        'heading' => &$heading,
        'backlink' => &$backlink,
        'rsslink' => &$rsslink,
    ));

    // vychozi implementace?
    if ('' === $output) {
        // rss odkaz
        if ($rsslink) {
            $output .= _templateRssLink(null, false);
        }

        // nadpis
        if ($heading) {
            $output .= _templateHeading();
        }

        // zpetny odkaz
        if ($backlink) {
            $output .= _templateBacklink();
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
function _templateHeading()
{
    global $_index;
    
    $output = '';

    if ($_index['heading_enabled']) {
        $heading = $_index[(null !== $_index['heading']) ? 'heading' : 'title'];

        // extend
        $output = Extend::buffer('tpl.heading', array('heading' => $heading));

        // vychozi implementace?
        if ('' === $output) {
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
function _templateBacklink()
{
    // extend
    $output = Extend::buffer('tpl.backlink');

    // vychozi implementace?
    if ('' === $output && null !== $GLOBALS['_index']['backlink']) {
        $output = '<div class="backlink"><a href="' . _e($GLOBALS['_index']['backlink']) . '">&lt; ' . $GLOBALS['_lang']['global.return'] . "</a></div>\n";
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
function _templateRssLink($url = null, $inline = true)
{
    // deaktivovane RSS / nenastavena adresa?
    if (!_rss || null === $url && null === $GLOBALS['_index']['rsslink']) {
        return '';
    }

    // pouzit RSS adresu aktualni stranky
    if (null === $url) {
        $url = $GLOBALS['_index']['rsslink'];
    }

    // extend
    $output = Extend::buffer('tpl.rsslink', array(
        'url' => $url,
        'inline' => $inline,
    ));

    // vychozi implementace?
    if ('' === $output) {
        if (!$inline) {
            $output .= '<div class="rsslink">';
        }
        $output .= '<a' . ($inline ? ' class="rsslink-inline"' : '') . ' href="' . _e($url) . '" title="' . $GLOBALS['_lang']['rss.linktitle'] . '"><img src="' . _templateImage("icons/rss.png") . "\" alt=\"rss\" class=\"icon\"></a>";
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
function _templateLinks()
{
    global $_lang;

    return
        "<li><a href=\"https://sunlight-cms.org/\">SunLight CMS</a></li>\n"
        . ((!_adminlinkprivate || (_login && _priv_administration)) ? '<li><a href="' . _link('admin/') . '">' . $_lang['global.adminlink'] . "</a></li>\n" : '');
}

/**
 * Sestavit adresu k obrazku aktualniho motivu
 *
 * @param string $path subcesta k souboru relativne ke slozce images aktualniho motivu
 * @return string
 */
function _templateImage($path)
{
    return $GLOBALS['_template']->getWebPath() . "/images/{$path}";
}

/**
 * Vykreslit menu
 *
 * @param int|null $ordStart minimalni poradove cislo
 * @param int|null $ordEnd   maximalni poradove cislo
 * @param string   $cssClass trida hlavniho tagu menu
 * @return string
 */
function _templateMenu($ordStart = null, $ordEnd = null, $cssClass = null)
{
    // kontrola prihlaseni v pripade neverejnych stranek
    if (!_login && _notpublicsite) {
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
        'tpl.menu.item',
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
function _templateTreeMenu(array $options)
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
    if (!_login && _notpublicsite) {
        return '';
    }

    // zjisteni aktivni stranky
    list($activeId) = PageManager::getActive();

    // pouziti aktivni stranky
    if (-1 == $options['page_id']) {
        if (null === $activeId) {
            return '';
        } else {
            $options['page_id'] = $activeId;
        }
    }

    // zjistit uroven a hloubku
    try {
        list($level, $depth) = PageManager::getTreeReader()->getLevelAndDepth($options['page_id']);
        if (null !== $options['max_depth']) {
            $depth = min($options['max_depth'], $depth);
        }
    } catch (RuntimeException $e) {
        // stranka nenalezena
        return _dev ? _e($e->getMessage()) : '';
    }

    // nacist stranky
    $filter = new PageTreeFilter(array(
        'ord_start' => $options['ord_start'],
        'ord_end' => $options['ord_end'],
        'ord_level' => null === $options['page_id'] ? $level : $level + 1,
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
 * @return string
 */
function _templateBreadcrumbs($breadcrumbs = array())
{
    global $_index;

    // zjistit aktivni stranku a jeji uroven
    list($rootId, $rootData) = PageManager::getActive();
    if (null !== $rootData) {
        $rootLevel = $rootData['node_level'];
    } else {
        $rootLevel = null;
    }

    // pridat stranky
    if (null !== $rootId) {
        foreach (PageManager::getPath($rootId, $rootLevel) as $page) {
            $breadcrumbs[] = array(
                'title' => $page['title'],
                'url' => _linkRoot($page['id'], $page['slug']),
            );
        }
    }

    // pridat modul
    if ($_index['is_module']) {
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
    if (!empty($breadcrumbs) && '' === $output) {
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
function _templateTitle()
{
    // overload pluginem
    $title = null;
    Extend::call('tpl.title', array('title' => &$title, 'head' => false));
    if (!isset($title)) {
        $title = $GLOBALS['_index']['title'];
    }

    echo $title;
}

/**
 * Ziskat titulek stranek
 *
 * @return string
 */
function _templateSiteTitle()
{
    return _title;
}

/**
 * Ziskat popis stranek
 *
 * @return string
 */
function _templateSiteDescription()
{
    return _description;
}

/**
 * Ziskat zakladni adresu stranek
 */
function _templateSiteUrl()
{
    return Core::$url . '/';
}

/**
 * Vykreslit uzivatelske menu
 *
 * @param bool $profileLink vykreslit odkaz na profil 1/0
 * @return string
 */
function _templateUserMenu($profileLink = true)
{
    global $_lang;

    // pripravit polozky
    $items = array();

    if (!_login) {
        // prihlaseni
        $items['login'] = array(
            _linkModule('login', 'login_form_return=' . rawurlencode($_SERVER['REQUEST_URI'])),
            $_lang['usermenu.login'],
        );
        if (_registration) {
            // registrace
            $items['reg'] = array(
                _linkModule('reg'),
                $_lang['usermenu.registration'],
            );
        }
    } else {
        // profil
        if ($profileLink) {
            $items['profile'] = array(
                _linkModule('profile', 'id=' . _loginname),
                $_lang['usermenu.profile'],
            );
        }

        // vzkazy
        if (_messages) {
            $messages_count = _userGetUnreadPmCount();
            if ($messages_count != 0) {
                $messages_count = " [{$messages_count}]";
            } else {
                $messages_count = '';
            }
            $items['messages'] = array(
                _linkModule('messages'),
                $_lang['usermenu.messages'] . $messages_count,
            );
        }

        // nastaveni
        $items['settings'] = array(
            _linkModule('settings'),
            $_lang['usermenu.settings'],
        );
    }

    if (_ulist && (!_notpublicsite || _login)) {
        // seznam uzivatelu
        $items['ulist'] = array(
            _linkModule('ulist'),
            $_lang['usermenu.ulist'],
        );
    }

    // odhlaseni
    if (_login) {
        $items['logout'] = array(
            _xsrfLink(_link("system/script/logout.php?_return=" . rawurlencode($_SERVER['REQUEST_URI']))),
            $_lang['usermenu.logout'],
        );
    }

    // vykreslit
    $output = Extend::buffer('tpl.usermenu', array('items' => &$items));
    if ('' === $output && !empty($items)) {
        $output = "<ul class=\"user-menu " . (_login ? 'logged-in' : 'not-logged-in') . "\">\n";
        foreach ($items as $id => $item) {
            $output .= "<li class=\"user-menu-{$id}\"><a href=\"{$item[0]}\">{$item[1]}</a></li>\n";
        }
        $output .= "</ul>\n";
    }

    return $output;
}

/**
 * Zjistit ID aktualni stranky
 *
 * @return int|null
 */
function _templateCurrentID()
{
    return $GLOBALS['_index']['id'];
}

/**
 * Zjistit, zda je aktualni obsah typu "stranka"
 *
 * @return bool
 */
function _templateCurrentIsPage()
{
    return $GLOBALS['_index']['is_page'];
}

/**
 * Zjistit, zda je aktualni obsah typu "clanek" (v kategorii)
 *
 * @return bool
 */
function _templateCurrentIsArticle()
{
    return
        $GLOBALS['_index']['is_page']
        && _page_category == $GLOBALS['_page']['type']
        && null !== $GLOBALS['_index']['segment'];
}

/**
 * Zjistit, zda je aktualni obsah typu "tema" (ve foru)
 *
 * @return bool
 */
function _templateCurrentIsTopic()
{
    return
        $GLOBALS['_index']['is_page']
        && _page_forum == $GLOBALS['_page']['type']
        && null !== $GLOBALS['_index']['segment'];
}

/**
 * Zjistit, zda je aktualni obsah typu "modul"
 *
 * @return bool
 */
function _templateCurrentIsModule()
{
    return $GLOBALS['_index']['is_module'];
}

/**
 * Zjisteni, zda je aktualni obsah hlavni strana
 *
 * @return bool
 */
function _templateCurrentIsIndex()
{
    return _templateCurrentIsPage() && $GLOBALS['_index']['id'] == _index_page_id;
}
