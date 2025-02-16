<?php

use Sunlight\Admin\Admin;
use Sunlight\GenericTemplates;
use Sunlight\Logger;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

// load code
$code = Request::post('code', '');

$output .= _buffer(function () use ($code) { ?>
    <?= Form::start('phpex') ?>
        <?= Admin::editor('php-eval', 'code', $code, ['mode' => 'code', 'format' => 'php-raw']) ?><br>
        <p><?= Form::input('submit', null, _lang('global.do'), ['class' => 'inputfat']) ?> <label><?= Form::input('checkbox', 'html', '1', ['checked' => Form::loadCheckbox('html')]) ?> <?= _lang('admin.other.phpex.html') ?></label></p>
    <?= Form::end('phpex') ?>
<?php });

if ($code === '') {
    return;
}

$html = isset($_POST['html']);
$output .= '<h2>' . _lang('global.result') . '</h2>';
$output .= '<div class="hr"><hr></div>';
$output .= "\n\n";

ob_start();

Logger::notice('system', 'Executed custom PHP code via admin module', ['code' => $code]);

try {
    eval($code);
} catch (Throwable $e) {
    $output .= GenericTemplates::renderException($e);
    $html = true;
}

$output .= $html ? ob_get_clean() : '<pre>' . _e(ob_get_clean()) . '</pre>';
