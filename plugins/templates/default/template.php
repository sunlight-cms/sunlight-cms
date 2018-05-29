<?php defined('_root') or exit ?>

<div id="wrapper">
    <div id="header">
        <div id="logo">
            <a href="<?php echo Sunlight\Template::siteUrl() ?>"><?php echo Sunlight\Template::siteTitle() ?></a>
            <p><?php echo Sunlight\Template::siteDescription() ?></p>
        </div>

        <?php echo Sunlight\Template::userMenu() ?>
    </div>

    <div id="menu">
        <?php echo Sunlight\Template::menu() ?>
    </div>

    <div id="page">
        <div id="content">
            <?php echo Sunlight\Template::content() ?>

            <div class="cleaner"></div>
        </div>
        <div id="sidebar">
            <?php echo Sunlight\Template::boxes('right') ?>
        </div>
        <div class="cleaner"></div>
    </div>
</div>
<div id="footer">
    <ul>
        <li><a href="http://templated.co/" rel="nofollow">TEMPLATED</a></li>
        <?php echo Sunlight\Template::links() ?>
    </ul>
</div>
