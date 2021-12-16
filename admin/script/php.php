<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\GenericTemplates;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Xsrf;

require '../../system/bootstrap.php';
Core::init('../../', [
    'env' => Core::ENV_ADMIN,
]);

/* ---  vystup  --- */

if (!User::isSuperAdmin()) {
    Response::redirect(Router::adminIndex(['absolute' => true]));
    exit;
}

echo GenericTemplates::renderHead();

$assets = Admin::themeAssets(Settings::get('adminscheme'), Settings::get('adminscheme_dark')) + ['extend_event' => null];

echo GenericTemplates::renderHeadAssets($assets);

?>
<title><?= _lang('admin.other.php.title') ?></title>
</head>

<body>
<div id="external-container">

<?php

// nacteni postdat
$process = false;
if (isset($_POST['code'])) {
    $code = Request::post('code');
    if (Xsrf::check()) {
        $process = true;
    }
}

?>

<h1><?= _lang('admin.other.php.title') ?></h1>

<form action="<?= _e(Router::path('admin/script/php.php')) ?>" method="post">
<textarea name="code" rows="25" cols="94" class="areabig editor" data-editor-mode="code" data-editor-format="php-raw"><?php if (isset($code)) echo _e($code); ?></textarea><br>
<p><input class="inputfat" type="submit" value="<?= _lang('global.do') ?>">  <label><input type="checkbox" name="html" value="1"<?= Form::activateCheckbox(isset($_POST['html']) ? 1 : 0) ?>> <?= _lang('admin.other.php.html') ?></label></p>
<?= Xsrf::getInput() ?>
</form>

<?php

if ($process) {
    $html = isset($_POST['html']);
    echo '<h2>' . _lang('global.result') . '</h2>';
    echo '<div class="hr"><hr></div>';
    echo "\n\n";

    ob_start();

    try {
        eval($code);
    } catch (Throwable $e) {
        echo Core::renderException($e);
        $html = true;
    }

    $output = ob_get_clean();
    echo $html ? $output : '<pre>' . _e($output) . '</pre>';
}

?>

</div>
</body>
</html>
