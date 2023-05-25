<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// load code
$process = false;
$code = '';

if (isset($_POST['code'])) {
    $code = Request::post('code');

    if (Xsrf::check()) {
        $process = true;
    }
}

$output .= _buffer(function () use ($code) { ?>
    <form method="post">
        <?= Admin::editor('php-eval', 'code', _e($code), ['mode' => 'code', 'format' => 'php-raw']) ?><br>
        <p><input class="inputfat" type="submit" value="<?= _lang('global.do') ?>">  <label><input type="checkbox" name="html" value="1"<?= Form::activateCheckbox(isset($_POST['html']) ? 1 : 0) ?>> <?= _lang('admin.other.phpex.html') ?></label></p>
        <?= Xsrf::getInput() ?>
    </form>
<?php });

if ($process) {
    $html = isset($_POST['html']);
    $output .= '<h2>' . _lang('global.result') . '</h2>';
    $output .= '<div class="hr"><hr></div>';
    $output .= "\n\n";

    ob_start();

    try {
        eval($code);
    } catch (Throwable $e) {
        $output .= Core::renderException($e);
        $html = true;
    }

    $output .= $html ? ob_get_clean() : '<pre>' . _e(ob_get_clean()) . '</pre>';
}
