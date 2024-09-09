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
        <div id="content"<?php if (Template::hasBoxes('right')): ?> class="with-sidebar"<?php endif ?>>
            <?= Template::heading() ?>
            <?= Template::backlink() ?>
            <?= Template::content() ?>

            <div class="cleaner"></div>
        </div>

        <?php if (Template::hasBoxes('right')): ?>
        <div id="sidebar">
            <?= Template::boxes('right') ?>
        </div>
        <div class="cleaner"></div>
        <?php endif ?>
    </div>
</div>
<div id="footer">
    <ul>
        <?= Template::links() ?>
    </ul>
</div>
