<?php

namespace SunlightExtend\Fancybox;

use Sunlight\Plugin\ExtendPlugin;

/**
 * Fancybox plugin
 *
 * @author ShiraNai7 <shira.cz>
 */
class FancyboxPlugin extends ExtendPlugin
{
    /**
     * Load CSS and JS
     *
     * @param array $args
     */
    public function onHead(array $args)
    {
        $basePath = $this->getWebPath() . '/Resources';

        $args['css']['lightbox'] = $basePath . '/style.css';
        $args['js']['lightbox'] = $basePath . '/script.js';
    }
}
