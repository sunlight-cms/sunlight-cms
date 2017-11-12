<?php

use Sunlight\Database\Database;
use Sunlight\Database\Doctrine\Console;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

if (!defined('_root')) {
    exit;
}

$output .= _buffer(function () { ?>
    <form method="post">
        <input type="text" name="input" class="inputbig cli-input">
        <input type="submit" value="<?php echo _lang('global.send') ?>" class="button">
        <a href="index.php?p=plugins-doctrine-console"><?php echo _lang('global.reset') ?></a>
        <?php echo _xsrfProtect() ?>
    </form>
<?php });

$cli = Console::createApplication(Database::getEntityManager());
$cli->setAutoExit(false);
$cli->setTerminalDimensions(160, 1000);
$cli->setCatchExceptions(false);

$cliInput = new StringInput(_post('input', ''));
$cliOutput = new BufferedOutput();

$e = null;
try {
    $cli->run($cliInput, $cliOutput);
} catch (\Exception $e) {
} catch (Throwable $e) {
}

$cliOutputString = $cliOutput->fetch();

if ($cliOutputString !== '') {
    $output .= '<pre class="cli-output">' . _e($cliOutputString) . '</pre>';
}

if ($e !== null) {
    $output .= \Sunlight\Core::renderException($e);
}
