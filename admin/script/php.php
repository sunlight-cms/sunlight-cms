<?php

use Sunlight\Core;

require '../../system/bootstrap.php';
Core::init('../../', array(
    'env' => Core::ENV_ADMIN,
));

/* ---  vystup  --- */

if (!_priv_super_admin) {
    exit;
}
echo Sunlight\Generic::renderHead();

$assets = \Sunlight\Admin\Admin::themeAssets(_adminscheme, \Sunlight\Admin\Admin::themeIsDark()) + array('extend_event' => null);

echo \Sunlight\Generic::renderHeadAssets($assets);

?>
<title><?php echo _lang('admin.other.php.title'); ?></title>
</head>

<body>
<div id="external-container">

<?php

// nacteni postdat
$process = false;
if (isset($_POST['code'])) {
    $code = \Sunlight\Util\Request::post('code');
    if (\Sunlight\Xsrf::check()) {
        $process = true;
    }
}

?>

<h1><?php echo _lang('admin.other.php.title'); ?></h1>

<form action="php.php" method="post">
<textarea name="code" rows="25" cols="94" class="areabig editor" data-editor-mode="code" data-editor-format="php-raw"><?php if (isset($code)) echo _e($code); ?></textarea><br>
<p><input class="inputfat" type="submit" value="<?php echo _lang('global.do'); ?>">  <label><input type="checkbox" name="html" value="1"<?php echo \Sunlight\Util\Form::activateCheckbox(isset($_POST['html']) ? 1 : 0); ?>> <?php echo _lang('admin.other.php.html'); ?></label></p>
<?php echo \Sunlight\Xsrf::getInput(); ?>
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
