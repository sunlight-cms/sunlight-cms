<?php
use Sunlight\Template;
defined('_root') or exit
?>

<div id="wrapper">
    <div id="header">
        <div id="logo">
            <a href="<?php echo Template::siteUrl() ?>"><?php echo Template::siteTitle() ?></a>
            <p><?php echo Template::siteDescription() ?></p>
        </div>

        <?php echo Template::userMenu() ?>
    </div>

    <div id="menu">
        <?php echo Template::menu() ?>
    </div>

    <div id="page">
        <div id="content">
            <?php echo Template::content() ?>

            <div class="cleaner"></div>
        </div>
        <div id="sidebar">
            <?php echo Template::boxes('right') ?>
        </div>
        <div class="cleaner"></div>
    </div>
</div>
<div id="footer">
    <ul>
        <li><a href="http://templated.co/" rel="nofollow">TEMPLATED</a></li>
        <?php echo Template::links() ?>
    </ul>
</div>
