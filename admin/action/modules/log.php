<?php

use Sunlight\Core;
use Sunlight\Log\LogQuery;
use Sunlight\Logger;
use Sunlight\Paginator;
use Sunlight\Router;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;

defined('SL_ROOT') or exit;

$query = new LogQuery();

// load query params
$queryParams = [
    'maxLevel' => 'int',
    'category' => 'string',
    'since' => 'timestamp',
    'until' => 'timestamp',
    'keyword' => 'string',
    'method' => 'string',
    'urlKeyword' => 'string',
    'ip' => 'string',
    'userId' => 'int',
    'limit' => 'int',
];

$queryParamValues = [];
$queryParamErrors = [];

foreach ($queryParams as $param => $type) {
    $value = $queryParamValues[$param] = Request::get($param, '');

    if ($value === '') {
        continue;
    }

    switch ($type) {
        case 'int':
            $value = (int) $value;

            if ($value < 0) {
                $queryParamErrors[$param] = true;
                continue 2;
            }
            break;
        case 'string':
            $value = trim($value);
            break;
        case 'timestamp':
            $value = strtotime($value);

            if ($value === false) {
                $queryParamErrors[$param] = true;
                continue 2;
            }
            break;
    }

    $query->{$param} = $value;
}

$query->desc = isset($_GET['desc']) || !isset($_GET['search']);

// paginator
$totalResults = Logger::getTotalResults($query);
$paginator = Paginator::paginate(Core::getCurrentUrl()->buildRelative(), $query->limit, $totalResults);
$query->offset = $paginator['first'];

// get entries
$entries = Logger::search($query);

// output
$output .= _buffer(function () use ($query, $queryParamValues, $queryParamErrors, $totalResults, $entries) { ?>
<form method="get" class="log-search">
    <?= Form::input('hidden', 'p', 'log') ?>

    <table class="formtable">
        <tr>
            <!-- time range -->
            <th><?= _lang('global.time') ?></th>
            <td>
                <select
                    class="log-time-presets"
                    onchange="const o = this.options[this.selectedIndex]; this.form.since.value = o.dataset.since; this.form.until.value = o.dataset.until; this.value = '';"
                >
                    <option value="">🕒</option>
                    <option data-since="-15 minutes" data-until=""><?= _lang('admin.log.search.time.15mins') ?></option>
                    <option data-since="-1 hour" data-until=""><?= _lang('admin.log.search.time.hour') ?></option>
                    <option data-since="today 00:00" data-until=""><?= _lang('admin.log.search.time.today') ?></option>
                    <option data-since="yesterday 00:00" data-until="yesterday 23:59:59"><?= _lang('admin.log.search.time.yesterday') ?></option>
                    <option data-since="-3 days 00:00" data-until=""><?= _lang('admin.log.search.time.3days') ?></option>
                    <option data-since="-7 days 00:00" data-until=""><?= _lang('admin.log.search.time.7days') ?></option>
                    <option data-since="-30 days 00:00" data-until=""><?= _lang('admin.log.search.time.30days') ?></option>
                    <option data-since="" data-until=""><?= _lang('admin.log.search.time.all') ?></option>
                </select>
                <?= Form::input('text', 'since', $queryParamValues['since'], ['class' => 'inputsmall' . (isset($queryParamErrors['since']) ? ' error-border' : '')]) ?>
                -
                <?= Form::input('text', 'until', $queryParamValues['until'], ['class' => 'inputsmall' . (isset($queryParamErrors['until']) ? ' error-border' : '')]) ?>
            </td>

            <!-- level -->
            <th><?= _lang('admin.log.level') ?></th>
            <td>
                <?= Form::select('maxLevel', ['' => ''] + Logger::LEVEL_NAMES, $query->maxLevel ?? '', ['class' => 'inputmax']) ?>
            </td>

            <!-- category -->
            <th><?= _lang('admin.log.category') ?></th>
            <td colspan="3">
                <?= Form::input('text', 'category', $query->category ?? '', ['class' => 'inputmax', 'list' => 'log-categories']) ?>
                <datalist id="log-categories">
                    <?php foreach (Logger::getCategories() as $category): ?>
                    <option value="<?= _e($category) ?>">
                        <?php endforeach ?>
                </datalist>
            </td>
        </tr>

        <tr>
            <!-- URL -->
            <th><?= _lang('admin.log.url') ?></th>
            <td>
                <?= Form::input('text', 'method', $query->method ?? '', ['class' => 'inputsmaller', 'list' => 'log-methods', 'placeholder' => _lang('admin.log.method')]) ?>
                <datalist id="log-methods">
                    <option value="GET">
                    <option value="POST">
                </datalist>

                <?= Form::input('text', 'urlKeyword', $query->urlKeyword ?? '', ['class' => 'inputmedium']) ?>
            </td>

            <!-- IP -->
            <th><?= _lang('admin.log.ip') ?></th>
            <td>
                <?= Form::input('text', 'ip', $query->ip ?? '', ['class' => 'inputmax']) ?>
            </td>

            <!-- user -->
            <th><?= _lang('admin.log.user_id') ?></th>
            <td>
                <?= Form::input('text', 'userId', $query->userId ?? '', ['class' => 'inputmax']) ?>
            </td>
        </tr>

        <tr>
            <!-- message -->
            <th><?= _lang('admin.log.message') ?></th>
            <td>
                <?= Form::input('text', 'keyword', $query->keyword ?? '', ['class' => 'inputmax']) ?>
            </td>

            <!-- paginator -->
            <th><?= _lang('admin.settings.paging') ?></th>
            <td colspan="3">
                <?= Form::input('number', 'limit', $query->limit, ['class' => 'inputsmaller', 'min' => 1, 'max' => 1000])?>
                <label><?= Form::input('checkbox', 'desc', '1', ['checked' => (bool) $query->desc]) ?> <?= _lang('admin.log.search.desc') ?></label>
            </td>
        </tr>

        <tr>
            <td></td>
            <td colspan="3">
                <button type="submit" name="search" value="1" class="button"><?= _lang('admin.log.search.submit') ?></button>
                <a href="<?= _e(Router::admin('log')) ?>" class="button"><?= _lang('global.reset') ?></a>
            </td>
        </tr>
    </table>
</form>

<table class="list list-max list-noborder log-list">
    <?php if (!empty($entries)): ?>
    <caption><?= _lang('admin.log.search.total', ['%count%' => _num($totalResults)]) ?></caption>
    <?php endif ?>
    <thead>
        <tr>
            <th class="cell-shrink"><?= _lang('global.time') ?></th>
            <th class="cell-shrink"><?= _lang('admin.log.level') ?></th>
            <th class="cell-shrink"><?= _lang('admin.log.category') ?></th>
            <th><?= _lang('admin.log.url') ?></th>
            <th class="cell-shrink"><?= _lang('global.action') ?></th>
        </tr>
    </thead>
    <?php foreach ($entries as $entry): ?>
    <tbody>
        <tr class="log-meta valign-top">
            <td class="cell-shrink"><strong><?= _e($entry->getDateTime()->format('Y-m-d H:i:s.u')) ?></strong></td>
            <td class="cell-shrink"><?= _e(Logger::LEVEL_NAMES[$entry->level]) ?></td>
            <td class="cell-shrink"><?= _e($entry->category) ?></td>
            <td class="log-url"><?= _e(StringHelper::ellipsis($entry->url ?? '-', 255, false)) ?></td>
            <td class="actions" rowspan="2">
                <a class="button" href="<?= Router::admin('log-detail', ['query' => ['id' => $entry->id]]) ?>">
                    <?= _lang('admin.log.detail.link') ?>
                </a>
            </td>
        </tr>
        <tr class="log-message">
            <td colspan="4">
                <code><?= _e(StringHelper::ellipsis($entry->message, 1024, false)) ?></code>
            </td>
        </tr>
    </tbody>
    <?php endforeach ?>

    <?php if (empty($entries)): ?>
    <tbody>
        <tr>
            <td colspan="5">
                <?= _lang('global.nokit') ?>
            </td>
        </tr>
    </tbody>
    <?php endif ?>
</table>

<?php });

$output .= $paginator['paging'];
