<?php use Sunlight\Template; ?>

<div id="wrapper">
    <nav id="user-menu">
        <?= Template::userMenu() ?>
    </nav>

    <header id="header">
        <a id="logo" href="<?= Template::siteUrl() ?>"><?= Template::siteTitle() ?></a>
        <p><?= Template::siteDescription() ?></p>
        <?= Template::menu() ?>
    </header>

    <main id="content">
        <section>
            <?= Template::backlink() ?>
            <?= Template::heading() ?>
            <?= Template::content() ?>
        </section>

        <aside>
            <?= Template::boxes('right') ?>
        </aside>
    </main>

    <footer id="footer">
        <ul>
            <?= Template::links() ?>
        </ul>
    </footer>
</div>
