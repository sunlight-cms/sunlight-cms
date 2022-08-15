<?php

use Sunlight\Extend;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\ArgList;
use Sunlight\Util\StringManipulator;

defined('SL_ROOT') or exit;

// nacteni dat stranky
$_page = Page::find($segments);
if ($_page === false) {
    $_index->notFound();
    return;
}

// parametry stranky
$_index->url = Router::page($_page['id'], $_index->slug);
$_index->bodyClasses[] = 't-page';
if ($_index->slug !== null) {
    $_index->bodyClasses[] = 'p-' . StringManipulator::slugify($_index->slug, true, '_');
} elseif ($_page['id'] == Settings::get('index_page_id')) {
    $_index->bodyClasses[] = 'homepage';
}

if ($_index->slug !== null && ($slug_length = strlen($_page['slug'])) < strlen($_index->slug)) {
    $segment = substr($_index->slug, $slug_length + 1);
} else {
    $segment = null;
}

// meta
if ($_page['description'] !== '') {
    $_index->description = $_page['description'];
}

// motiv
if ($_page['layout'] !== null) {
    $_index->changeTemplate($_page['layout']);
}

// kontrola typu pristupu k hlavni strane
if (
    $_page['id'] == Settings::get('index_page_id')
    && !empty($segments)
    && $segment === null
) {
    if (!Settings::get('pretty_urls')) {
        $_url->remove('p');
    }

    $_index->redirect(Router::page($_page['id'], $_page['slug'], null, ['query' => $_url->getQuery()]));
    return;
}

if ($segment !== null) {
    // zkontrolovat, zda stranka podporuje segment
    if ($_page['type'] == Page::CATEGORY || $_page['type'] == Page::FORUM) {
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
        $_index->notFound();
        return;
    }
}

// presmerovani na hezkou adresu
if (Settings::get('pretty_urls') && !$_index->isRewritten && !empty($segments)) {
    $_index->redirect($_index->url, true);
    return;
}

// test pristupu
if (!User::checkPublicAccess($_page['public'], $_page['level'])) {
    $_index->unauthorized();
    return;
}

// nastaveni atributu
$_index->id = $_page['id'];
$_index->title = $_page['title'];
if ($_page['heading'] !== '') {
    $_index->heading = $_page['heading'];
}
$_index->headingEnabled = (bool) $_page['show_heading'];
$_index->segment = $segment;

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
$types = Page::getTypes();
$type_name = $types[$_page['type']];
$script = null;

// urceni skriptu
if ($_page['type'] == Page::PLUGIN) {
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
    $script = SL_ROOT . 'system/action/pages/' . $type_name . '.php';
}

// vykresleni stranky
$extend_args = Extend::args($output, ['page' => &$_page, 'script' => &$script]);

Extend::call('page.all.before', $extend_args);
Extend::call('page.' . $type_name . '.before', $extend_args);

$extend_args = Extend::args($output, ['page' => &$_page]);

require $script;

Extend::call('page.' . $type_name . '.after', $extend_args);
Extend::call('page.all.after', $extend_args);
