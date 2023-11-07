<?php

use Sunlight\Extend;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\ArgList;
use Sunlight\Util\StringHelper;

defined('SL_ROOT') or exit;

// load page
$_page = Page::find($segments);

if ($_page === false) {
    $_index->notFound();
    return;
}

// basic meta data
$_index->bodyClasses[] = 't-page';

if ($_index->slug !== null) {
    $_index->bodyClasses[] = 'p-' . StringHelper::slugify($_index->slug, ['extra' => '_']);
} elseif ($_page['id'] == Settings::get('index_page_id')) {
    $_index->bodyClasses[] = 'homepage';
}

if ($_index->slug !== null && ($slug_length = strlen($_page['slug'])) < strlen($_index->slug)) {
    $segment = substr($_index->slug, $slug_length + 1);
} else {
    $segment = null;
}

$_index->url = Router::page($_page['id'], $_page['slug'], $segment);

if ($_page['description'] !== '') {
    $_index->description = $_page['description'];
}

// change template
if ($_page['layout'] !== null) {
    $_index->changeTemplate($_page['layout']);
}

// check URL of index page
if (
    $_page['id'] == Settings::get('index_page_id')
    && !empty($segments)
    && $segment === null
) {
    $_index->redirect(Router::page($_page['id'], $_page['slug'], null, ['query' => $_url->getQuery()]));
    return;
}

if ($segment !== null) {
    // check if segment is supported
    if ($_page['type'] == Page::CATEGORY || $_page['type'] == Page::FORUM) {
        $segment_support = true;
    } else {
        $segment_support = false;
    }

    // allow plugins to implement segment logic
    Extend::call('page.segment', [
        'segment' => $segment,
        'page' => $_page,
        'support' => &$segment_support,
    ]);

    // 404 if segment is not supported
    if (!$segment_support) {
        $_index->notFound();
        return;
    }
}

// check access
if (!User::checkPublicAccess($_page['public'], $_page['level'])) {
    $_index->unauthorized();
    return;
}

// more meta data
$_index->id = $_page['id'];
$_index->title = $_page['title'];

if ($_page['heading'] !== '') {
    $_index->heading = $_page['heading'];
}

$_index->headingEnabled = (bool) $_page['show_heading'];
$_index->segment = $segment;

// page events
if ($_page['events'] !== null) {
    foreach (ArgList::parse($_page['events']) as $page_event) {
        $page_event_parts = explode(':', $page_event, 2);
        Extend::call('page.event.' . $page_event_parts[0], [
            'arg' => $page_event_parts[1] ?? null,
            'page' => &$_page,
        ]);
    }
}

// prepare to render page
$id = $_page['id'];
$type_name = Page::TYPES[$_page['type']];
$script = null;

// determine script
if ($_page['type'] == Page::PLUGIN) {
    // plugin page
    $script = null;
    Extend::call('page.plugin.' . $_page['type_idt'], [
        'page' => &$_page,
        'script' => &$script,
    ]);

    if ($script === null) {
        throw new RuntimeException(sprintf('No handler for plugin page type "%s"', $_page['type_idt']));
    }
} else {
    // other types
    $script = SL_ROOT . 'system/action/pages/' . $type_name . '.php';
}

// render page
$extend_args = Extend::args($output, ['page' => &$_page, 'script' => &$script]);

Extend::call('page.all.before', $extend_args);
Extend::call('page.' . $type_name . '.before', $extend_args);

$extend_args = Extend::args($output, ['page' => &$_page]);

require $script;

Extend::call('page.' . $type_name . '.after', $extend_args);
Extend::call('page.all.after', $extend_args);
