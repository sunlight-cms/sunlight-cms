<?php

use Kuria\Debug\Dumper;
use Sunlight\Database\Database as DB;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Json;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

// get entry
$entry = Logger::get(Request::get('id', ''));

if ($entry === null) {
    $output .= Message::warning(_lang('admin.log.detail.not_found'));
    return;
}

// process user ID
if ($entry->userId !== null) {
    $userQuery = User::createQuery(null, '');
    $user = DB::queryRow(
        'SELECT ' . $userQuery['column_list']
        . ' FROM ' . DB::table('user') . ' u'
        . ' ' . $userQuery['joins']
        . ' WHERE u.id=' . DB::val($entry->userId)
    );

    $userInfo = sprintf('ID = %d', $entry->userId);

    if ($user !== false) {
        $userInfo .= ' (' . Router::user($user, [
            'custom_link' => Router::admin('users-edit', ['query' => ['id' => $user['username']]]),
            'new_window' => false,
        ]) . ')';
    } else {
        $userInfo .= ' (' . _lang('admin.log.detail.nonexistent_user') . ')';
    }
} else {
    $userInfo = '-';
}

// parse context
$parsedContext = [];

if ($entry->context !== null) {
    $parsedContext = Json::decode($entry->context);
}

// output
$output .= _buffer(function () use ($entry, $parsedContext, $userInfo) { ?>
    <table class="list list-max table-collapse">
        <caption>
            <code><?= nl2br(_e($entry->message), false) ?></code>
        </caption>

        <tbody>
            <tr>
                <th class="cell-shrink"><?= _lang('global.time') ?></th>
                <td><?= _e($entry->getDateTime()->format('Y-m-d H:i:s.u')) ?></td>
            </tr>
            <tr>
                <th class="cell-shrink"><?= _lang('global.id') ?></th>
                <td><code><?= _e($entry->id) ?></code></td>
            </tr>
            <tr>
                <th class="cell-shrink"><?= _lang('admin.log.level') ?></th>
                <td><?= _e(Logger::LEVEL_NAMES[$entry->level]) ?></td>
            </tr>
            <tr>
                <th class="cell-shrink"><?= _lang('admin.log.category') ?></th>
                <td><?= _e($entry->category) ?></td>
            </tr>
            <tr>
                <th class="cell-shrink"><?= _lang('admin.log.method') ?></th>
                <td><code><?= _e($entry->method ?? '-') ?></code></td>
            </tr>
            <tr>
                <th class="cell-shrink"><?= _lang('admin.log.url') ?></th>
                <td class="mobile-text-wrap"><?= _e($entry->url ?? '-') ?></td>
            </tr>
            <tr>
                <th class="cell-shrink"><?= _lang('admin.log.ip') ?></th>
                <td><?= _e($entry->ip ?? '-') ?></td>
            </tr>
            <tr>
                <th class="cell-shrink"><?= _lang('admin.log.user_agent') ?></th>
                <td><?= _e($entry->userAgent ?? '-') ?></td>
            </tr>
            <tr>
                <th class="cell-shrink"><?= _lang('admin.log.user_id') ?></th>
                <td><?= $userInfo ?></td>
            </tr>
        </tbody>

        <?php if (!empty($parsedContext)): ?>
        <tbody>
            <?php foreach ($parsedContext as $key => $value): ?>
                <tr>
                    <th><code><?= _e($key) ?></code></th>
                    <td><pre><?= _e(is_string($value) ? $value : Dumper::dump($value)) ?></pre></td>
                </tr>
            <?php endforeach ?>
        </tbody>
        <?php endif ?>
    </table>
<?php });
