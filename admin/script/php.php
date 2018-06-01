<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Generic;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

require '../../system/bootstrap.php';
Core::init('../../', array(
    'env' => Core::ENV_ADMIN,
));

/* ---  vystup  --- */

if (!_priv_super_admin) {
    exit;
}
echo \Sunlight\Generic::renderHead();

$assets = Admin::themeAssets(_adminscheme, Admin::themeIsDark()) + array('extend_event' => null);

echo Generic::renderHeadAssets($assets);

?>
<title><?php echo _lang('admin.other.php.title'); ?></title>
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

<h1><?php echo _lang('admin.other.php.title'); ?></h1>

<form action="php.php" method="post">
<textarea name="code" rows="25" cols="94" class="areabig editor" data-editor-mode="code" data-editor-format="php-raw"><?php if (isset($code)) echo _e($code); ?></textarea><br>
<p><input class="inputfat" type="submit" value="<?php echo _lang('global.do'); ?>">  <label><input type="checkbox" name="html" value="1"<?php echo Form::activateCheckbox(isset($_POST['html']) ? 1 : 0); ?>> <?php echo _lang('admin.other.php.html'); ?></label></p>
<?php echo Xsrf::getInput(); ?>
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
    } catch (\Exception $e) {
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
