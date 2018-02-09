<?php

use Sunlight\Extend;
use Sunlight\Page\PageManager;

if (!defined('_root')) {
    exit;
}

// nacteni dat stranky
$_page = _findPage($segments);
if ($_page === false) {
    $_index['is_found'] = false;
    return;
}

// url stranky
$_index['url'] = _linkRoot($_page['id'], $_index['slug']);

// segment stranky
if ($_index['slug'] !== null && ($slug_length = strlen($_page['slug'])) < strlen($_index['slug'])) {
    $segment = substr($_index['slug'], $slug_length + 1);
} else {
    $segment = null;
}

// meta
if ($_page['description'] !== '') {
    $_index['description'] = $_page['description'];
}

// motiv
if ($_page['layout'] !== null) {
    _templateSwitch($_page['layout']);
}

// kontrola typu pristupu k hlavni strane
if (
    $_page['id'] == _index_page_id
    && !empty($segments)
    && $segment === null
) {
    if (!_pretty_urls) {
        $_url->remove('p');
    }

    $_index['redirect_to'] = _addParamsToUrl(
        _linkRoot($_page['id'], $_page['slug'], null, true),
        $_url->getQueryString(),
        false
    );
    return;
}

if ($segment !== null) {
    // zkontrolovat, zda stranka podporuje segment
    if ($_page['type'] == _page_category || $_page['type'] == _page_forum) {
        $segment_support = true;
    } else {
        $segment_support = false;
    }

    // umoznit pluginum urcit stav podpory segmentu
    Extend::call('page.segment', array(
        'segment' => $segment,
        'page' => $_page,
        'support' => &$segment_support,
    ));

    // stranka nenalezena, pokud nepodporuje segment
    if (!$segment_support) {
        $_index['is_found'] = false;
        return;
    }
}

// presmerovani na hezkou adresu
if (_pretty_urls && !$_index['is_rewritten'] && !empty($segments)) {
    $_index['redirect_to'] = $_index['url'];
    $_index['redirect_to_permanent'] = true;
    return;
}

// test pristupu
if (!_publicAccess($_page['public'], $_page['level'])) {
    $_index['is_accessible'] = false;
    return;
}

// nastaveni atributu
$_index['id'] = $_page['id'];
$_index['title'] = $_page['title'];
if ($_page['heading'] !== '') {
    $_index['heading'] = $_page['heading'];
}
$_index['heading_enabled'] = (bool) $_page['show_heading'];
$_index['segment'] = $segment;

// udalosti stranky
if ($_page['events'] !== null) {
    foreach (_parseArguments($_page['events']) as $page_event) {
        $page_event_parts = explode(':', $page_event, 2);
        Extend::call('page.event.' . $page_event_parts[0], array(
            'arg' => isset($page_event_parts[1]) ? $page_event_parts[1] : null,
            'page' => &$_page,
        ));
    }
}

// priprava vykresleni stranky
$id = $_page['id'];
$types = PageManager::getTypes();
$type_name = $types[$_page['type']];
$script = null;

// urceni skriptu
if ($_page['type'] == _page_plugin) {
    // plugin stranka
    $script = null;
    Extend::call('page.plugin.' . $_page['type_idt'], array(
        'page' => &$_page,
        'script' => &$script,
    ));

    if ($script === null) {
        throw new RuntimeException(sprintf('No handler for plugin page type "%s"', $_page['type_idt']));
    }
} else {
    // ostatni typy
    $script = _root . 'system/action/pages/' . $type_name . '.php';
}

// vykresleni stranky
$extend_args = Extend::args($output, array('page' => &$_page, 'script' => &$script));

Extend::call('page.all.pre', $extend_args);
Extend::call('page.' . $type_name . '.pre', $extend_args);

$extend_args = Extend::args($output, array('page' => &$_page));

require $script;

Extend::call('page.' . $type_name . '.post', $extend_args);
Extend::call('page.all.post', $extend_args);
