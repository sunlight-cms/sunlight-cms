<?php
use Sunlight\Template;
defined('_root') or exit
?>

<div id="header">
    <h2>
        <a href="<?= Template::siteUrl() ?>">
            <?= Template::siteTitle() ?>
        </a>
    </h2>

    <h3>
        <?= Template::siteDescription() ?>
    </h3>

    <?= Template::userMenu() ?>
</div>

<div id="content">
    <div id="colOne">
        <?= Template::boxes('left') ?>
    </div>

    <div id="colTwo"><div class="bg2">
        <?= Template::content() ?>
    </div></div>
</div>

<div id="footer">
    <ul>
        <li><a href="https://www.freecsstemplates.org/" rel="nofollow">Free CSS Templates</a></li>
        <?= Template::links() ?>
    </ul>
</div>
