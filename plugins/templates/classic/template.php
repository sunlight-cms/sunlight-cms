<?php
use Sunlight\Template;
defined('_root') or exit
?>

<div id="header">
    <h2>
        <a href="<?php echo Template::siteUrl() ?>">
            <?php echo Template::siteTitle() ?>
        </a>
    </h2>

    <h3>
        <?php echo Template::siteDescription() ?>
    </h3>

    <?php echo Template::userMenu() ?>
</div>

<div id="content">
    <div id="colOne">
        <?php echo Template::boxes('left') ?>
    </div>

    <div id="colTwo"><div class="bg2">
        <?php echo Template::content() ?>
    </div></div>
</div>

<div id="footer">
    <ul>
        <li><a href="http://www.freecsstemplates.org/" rel="nofollow">Free CSS Templates</a></li>
        <?php echo Template::links() ?>
    </ul>
</div>
