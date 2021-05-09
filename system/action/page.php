<?php

use Sunlight\Extend;
use Sunlight\Page\PageManager;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\ArgList;
use Sunlight\Util\StringManipulator;
use Sunlight\Util\UrlHelper;

defined('_root') or exit;

// nacteni dat stranky
$_page = PageManager::find($segments);
if ($_page === false) {
    $_index['type'] = _index_not_found;
    return;
}

// parametry stranky
$_index['url'] = Router::page($_page['id'], $_index['slug']);
$_index['body_classes'][] = 't-page';
if ($_index['slug'] !== null) {
    $_index['body_classes'][] = 'p-' . StringManipulator::slugify($_index['slug'], true, '_');
} elseif ($_page['id'] == _index_page_id) {
    $_index['body_classes'][] = 'homepage';
}

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
    Template::change($_page['layout']);
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

    $_index['type'] = _index_redir;
    $_index['redirect_to'] = UrlHelper::appendParams(
        Router::page($_page['id'], $_page['slug'], null, true),
        $_url->getQueryString()
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
    Extend::call('page.segment', [
        'segment' => $segment,
        'page' => $_page,
        'support' => &$segment_support,
    ]);

    // stranka nenalezena, pokud nepodporuje segment
    if (!$segment_support) {
        $_index['type'] = _index_not_found;
        return;
    }
}

// presmerovani na hezkou adresu
if (_pretty_urls && !$_index['is_rewritten'] && !empty($segments)) {
    $_index['type'] = _index_redir;
    $_index['redirect_to'] = $_index['url'];
    $_index['redirect_to_permanent'] = true;
    return;
}

// test pristupu
if (!User::checkPublicAccess($_page['public'], $_page['level'])) {
    $_index['type'] = _index_unauthorized;
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
    foreach (ArgList::parse($_page['events']) as $page_event) {
        $page_event_parts = explode(':', $page_event, 2);
        Extend::call('page.event.' . $page_event_parts[0], [
            'arg' => $page_event_parts[1] ?? null,
            'page' => &$_page,
        ]);
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
    Extend::call('page.plugin.' . $_page['type_idt'], [
        'page' => &$_page,
        'script' => &$script,
    ]);

    if ($script === null) {
        throw new RuntimeException(sprintf('No handler for plugin page type "%s"', $_page['type_idt']));
    }
} else {
    // ostatni typy
    $script = _root . 'system/action/pages/' . $type_name . '.php';
}

// vykresleni stranky
$extend_args = Extend::args($output, ['page' => &$_page, 'script' => &$script]);

Extend::call('page.all.before', $extend_args);
Extend::call('page.' . $type_name . '.before', $extend_args);

$extend_args = Extend::args($output, ['page' => &$_page]);

require $script;

Extend::call('page.' . $type_name . '.after', $extend_args);
Extend::call('page.all.after', $extend_args);
