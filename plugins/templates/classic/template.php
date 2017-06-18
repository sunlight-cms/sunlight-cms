<?php if (!defined('_root')) exit; ?>

<div id="header">
    <h2>
        <a href="<?php echo _templateSiteUrl() ?>">
            <?php echo _templateSiteTitle() ?>
        </a>
    </h2>

    <h3>
        <?php echo _templateSiteDescription() ?>
    </h3>

    <?php echo _templateUserMenu() ?>
</div>

<div id="content">
    <div id="colOne">
        <?php echo _templateBoxes('left') ?>
    </div>

    <div id="colTwo"><div class="bg2">
        <?php echo _templateContent() ?>
    </div></div>
</div>

<div id="footer">
    <ul>
        <li><a href="http://www.freecsstemplates.org/" rel="nofollow">Free CSS Templates</a></li>
        <?php echo _templateLinks() ?>
    </ul>
</div>
