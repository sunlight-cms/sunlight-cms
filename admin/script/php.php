<?php

require '../../system/bootstrap.php';
Sunlight\Core::init('../../', array(
    'env' => 'admin',
));

/* ---  vystup  --- */

if (!_priv_super_admin) {
    exit;
}
require _root . "system/html_start.php";
require _root . "admin/functions.php";

?>
<link href="style.php?s=<?php echo _adminscheme . (_adminIsDarkScheme() ? '&amp;d' : '') . '&amp;' . _cacheid; ?>" type="text/css" rel="stylesheet">
<title><?php echo _lang('admin.other.php.title'); ?></title>
</head>

<body>
<div id="external-container">

<?php

// nacteni postdat
$process = false;
if (isset($_POST['code'])) {
    $code = _post('code');
    if (_xsrfCheck()) {
        $process = true;
    }
}

?>

<h1><?php echo _lang('admin.other.php.title'); ?></h1>

<form action="php.php" method="post">
<textarea name="code" rows="25" cols="94" class="areabig"><?php if (isset($code)) echo _e($code); ?></textarea><br>
<p><input class="inputfat" type="submit" value="<?php echo _lang('global.do'); ?>">  <label><input type="checkbox" name="html" value="1"<?php echo _checkboxActivate(isset($_POST['html']) ? 1 : 0); ?>> <?php echo _lang('admin.other.php.html'); ?></label></p>
<?php echo _xsrfProtect(); ?>
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
        echo Sunlight\Core::renderException($e);
        $html = true;
    }

    $output = ob_get_clean();
    echo $html ? $output : '<pre>' . _e($output) . '</pre>';
}

?>

</div>
</body>
</html>
