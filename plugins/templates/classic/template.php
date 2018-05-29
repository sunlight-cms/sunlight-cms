<?php if (!defined('_root')) exit; ?>

<div id="header">
    <h2>
        <a href="<?php echo \Sunlight\Template::siteUrl() ?>">
            <?php echo \Sunlight\Template::siteTitle() ?>
        </a>
    </h2>

    <h3>
        <?php echo \Sunlight\Template::siteDescription() ?>
    </h3>

    <?php echo \Sunlight\Template::userMenu() ?>
</div>

<div id="content">
    <div id="colOne">
        <?php echo \Sunlight\Template::boxes('left') ?>
    </div>

    <div id="colTwo"><div class="bg2">
        <?php echo \Sunlight\Template::content() ?>
    </div></div>
</div>

<div id="footer">
    <ul>
        <li><a href="http://www.freecsstemplates.org/" rel="nofollow">Free CSS Templates</a></li>
        <?php echo \Sunlight\Template::links() ?>
    </ul>
</div>
