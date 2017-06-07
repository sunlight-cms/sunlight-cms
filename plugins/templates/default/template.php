<?php if (!defined('_root')) exit; ?>

<div id="wrapper">
    <div id="header">
        <div id="logo">
            <a href="<?php echo _templateSiteUrl() ?>"><?php echo _templateSiteTitle() ?></a>
            <p><?php echo _templateSiteDescription() ?></p>
        </div>

        <?php echo _templateUserMenu() ?>
    </div>

	<div id="menu">
		<?php echo _templateMenu() ?>
	</div>

	<div id="page">
        <div id="content">
            <?php echo _templateContent() ?>

            <div class="cleaner"></div>
        </div>
        <div id="sidebar">
            <?php echo _templateBoxes('right') ?>
        </div>
        <div class="cleaner"></div>
	</div>
</div>
<div id="footer">
    <ul>
        <li><a href="http://templated.co/" rel="nofollow">TEMPLATED</a></li>
        <?php echo _templateLinks() ?>
    </ul>
</div>
