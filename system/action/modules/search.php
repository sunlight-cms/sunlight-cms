<?php

use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Search\Search;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
    $_index->unauthorized();
    return;
}

if (!Settings::get('search')) {
    $_index->notFound();
    return;
}

$search = new Search();

if (isset($_GET['q']) && Xsrf::check(true)) {
    $search_query = trim(Request::get('q', ''));
    $empty_state = false;
} else {
    $search_query = '';
    $empty_state = true;
}

// form
$_index->title = _lang('mod.search');

$output .= _buffer(function () use ($search, $search_query, $empty_state) { ?>
    <p class="bborder"><?= _lang('mod.search.p') ?></p>

    <?= Form::start('full-search', ['action' => Router::module('search'), 'method' => 'get', 'class' => 'fullsearchform']) ?>
        <p>
            <?= Form::input('search', 'q', $search_query, ['class' => 'inputmedium']) ?>
            <?= Form::input('submit', null, _lang('mod.search.submit')) ?>
        </p>

        <p>
            <?= _lang('mod.search.where') ?>:
            <?php foreach ($search->getSources() as $key => $source): ?>
                <label><?= Form::input('checkbox', $key, '1', ['checked' => ($empty_state || isset($_GET[$key]))]) ?> <?= $source->getLabel() ?></label>
            <?php endforeach ?>
        </p>
    <?= Form::end('full-search') ?>
<?php });

// validate search query
if ($search_query === '') {
    return;
}

if (mb_strlen($search_query) < 3) {
    $output .= Message::warning(_lang('mod.search.minlength'));
    return;
}

// get results
$search->setQuery($search_query);

foreach ($search->getSources() as $key => $source) {
    $search->toggleSource($key, isset($_GET[$key]));
}

// output results
$result_count = 0;

foreach ($search->search() as $result) {
    if ($result_count === 0) {
        $output .= "<div class=\"list list-search\">\n";
    }

    $output .= _buffer(function () use ($result) { ?>
    <div class="list-item">
        <h2 class="list-title">
            <a href="<?= _e($result->link) ?>"><?= $result->title ?></a>
        </h2>

        <?php if ($result->perex !== null): ?>
        <p class="list-perex">
            <?= $result->perex ?>
        </p>
        <?php endif ?>

        <?php if (!empty($result->infos)): ?>
            <?= GenericTemplates::renderInfos($result->infos) ?>
        <?php endif ?>
    </div>
<?php });

    ++$result_count;
}

if ($result_count > 0) {
    $output .= "</div>\n";
} else {
    $output .= Message::ok(_lang('mod.search.noresult'));
}
