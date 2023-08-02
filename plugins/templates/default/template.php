<?php use Sunlight\Template; ?>

<div id="wrapper">
    <div id="header">
        <div id="logo">
            <a href="<?= Template::siteUrl() ?>"><?= Template::siteTitle() ?></a>
            <p><?= Template::siteDescription() ?></p>
        </div>

        <?= Template::userMenu() ?>
    </div>

    <div id="menu">
        <?= Template::menu() ?>
    </div>

    <div id="page">
        <div id="content">
            <?= Template::heading() ?>
            <?= Template::backlink() ?>
            <?= Template::content() ?>

            <div class="cleaner"></div>
        </div>
        <div id="sidebar">
            <?= Template::boxes('right') ?>
        </div>
        <div class="cleaner"></div>
    </div>
</div>
<div id="footer">
    <ul>
        <?= Template::links() ?>
    </ul>
</div>
