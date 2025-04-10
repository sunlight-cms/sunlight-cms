<?php

namespace Sunlight;

use Kuria\Debug\Exception;
use Sunlight\Util\StringHelper;
use Sunlight\Util\UrlHelper;

abstract class GenericTemplates
{
    /**
     * Render a date value
     *
     * @param int $timestamp UNIX timestamp
     * @param string|null $type short description of usage type for plugins
     */
    static function renderDate(int $timestamp, ?string $type = null): string
    {
        return self::renderTimestamp('date_format', $timestamp, $type);
    }

    /**
     * Render a date-time value
     *
     * @param int $timestamp UNIX timestamp
     * @param string|null $type short description of usage type for plugins
     */
    static function renderTime(int $timestamp, ?string $type = null): string
    {
        return self::renderTimestamp('time_format', $timestamp, $type);
    }

    /**
     * Render a file size
     */
    static function renderFilesize(int $bytes): string
    {
        if ($bytes >= 1000000) {
            return _num($bytes / 1000000) . ' MB';
        }

        return _num(ceil($bytes / 1000)) . ' kB';
    }

    /**
     * Render an IP address
     */
    static function renderIp(string $ip): string
    {
        if (User::$group['id'] != User::ADMIN_GROUP_ID) {
            // only admins see the actual IP, anonymize IP for anyone else
            $ip = substr(hash_hmac('sha256', $ip, Core::$secret), 0, 16);
        }

        return _lang('posts.anonym') . '@' . _e($ip);
    }

    /**
     * Render the beginning of an HTML document
     */
    static function renderHead(): string
    {
        $lang = _e(Core::$langPlugin->getIsoCode());
        $generator = '<meta name="generator" content="SunLight CMS">' . "\n";

        Extend::call('render.meta_generator', ['generator' => &$generator]);

        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
<meta charset="UTF-8">
{$generator}
HTML;
    }

    /**
     * Render HTML to insert CSS and JS into <head>
     *
     * Parameters supported in $assets:
     * --------------------------------
     * - meta             HTML inserted at the beginning
     * - css              array with paths to CSS files
     * - js               array with paths to JS files
     * - css_before       HTML inserted before <link> tags
     * - css_after        HTML inserted after <link> tags
     * - js_before        HTML inserted before <script> tags
     * - js_after         HTML inserted after <script> tags
     * - extend_event     extend event name
     * - favicon          true = link to favicon, false = no favicon, null = no output
     *
     * @param array{
     *     meta?: string,
     *     css?: string[],
     *     js?: string[],
     *     css_before?: string,
     *     css_after?: string,
     *     js_before?: string,
     *     js_after?: string,
     *     favicon?: bool|null,
     * } $assets see description
     */
    static function renderHeadAssets(array $assets): string
    {
        $html = '';
        $cacheParam = 'v=' . self::getAssetCacheHash();

        $assets += [
            'meta' => '',
            'css' => [],
            'js' => [],
            'css_before' => '',
            'css_after' => '',
            'js_before' => '',
            'js_after' => '',
            'favicon' => null,
        ];

        // extend
        if (isset($assets['extend_event'])) {
            Extend::call($assets['extend_event'], [
                'meta' => &$assets['meta'],
                'css' => &$assets['css'],
                'js' => &$assets['js'],
                'css_before' => &$assets['css_before'],
                'css_after' => &$assets['css_after'],
                'js_before' => &$assets['js_before'],
                'js_after' => &$assets['js_after'],
                'favicon' => &$assets['favicon'],
            ]);
        }

        // meta
        $html .= $assets['meta'];

        // css
        $html .= $assets['css_before'];

        foreach ($assets['css'] as $item) {
            $html .= "\n<link rel=\"stylesheet\" href=\"" . _e(UrlHelper::appendParams($item, $cacheParam)) . '" type="text/css">';
        }

        $html .= $assets['css_after'];

        // favicon
        if ($assets['favicon'] !== null) {
            $faviconPath = $assets['favicon']
                ? UrlHelper::appendParams(Router::path('favicon.ico'), $cacheParam)
                : 'data:,';

            $html .= "\n<link rel=\"icon\" href=\"" . _e($faviconPath) . '">';
        }

        // javascript
        $html .= $assets['js_before'];

        foreach ($assets['js'] as $item) {
            $html .= "\n<script src=\"" . _e(UrlHelper::appendParams($item, $cacheParam)) . '"></script>';
        }

        $html .= $assets['js_after'];

        return $html;
    }

    /**
     * Get a hash used for asset cache-busting purposes
     */
    static function getAssetCacheHash(): string
    {
        static $cacheHash;

        return $cacheHash ?? ($cacheHash = substr(hash_hmac('sha256', Core::VERSION . '$' . Settings::get('cacheid'), Core::$secret), 0, 8));
    }

    /**
     * Render a list of information
     *
     * Each item must have 1 or 2 elements: array(content) or array(label, content)
     *
     * @param array[] $infos
     */
    static function renderInfos(array $infos, string $class = 'list-info'): string
    {
        if (!empty($infos)) {
            $output = '<ul class="' . _e($class) . "\"\n>";

            foreach ($infos as $info) {
                if (isset($info[1])) {
                    $output .= "<li><strong>{$info[0]}:</strong> {$info[1]}</li>\n";
                } else {
                    $output .= "<li>{$info[0]}</li>\n";
                }
            }

            $output .= "</ul>\n";

            return $output;
        }

        return '';
    }

    /**
     * Render a list of messages
     *
     * Supported options:
     * ------------------
     * - lcfirst (1)      lowercase first letter of each message
     * - trim_dots (1)    remove dots at the end of each message
     * - escape (1)       escape HTML in messages
     * - show_keys (0)    render array keys
     *
     * @param string[] $messages
     * @param array{lcfirst?: bool|null, trim_dots?: bool|null, escape?: bool|null, show_keys?: bool|null} $options see description
     */
    static function renderMessageList(array $messages, array $options = []): string
    {
        $output = '';

        if (!empty($messages)) {
            $output .= "<ul class=\"message-list\">\n";

            foreach ($messages as $key => $item) {
                if ($options['lcfirst'] ?? true) {
                    $item = StringHelper::lcfirst($item);
                }

                if ($options['trim_dots'] ?? true) {
                    $item = rtrim($item, '.');
                }

                $output .= '<li>'
                    . (($options['show_keys'] ?? false) ? '<strong>' . _e($key) . '</strong>: ' : '')
                    . (($options['escape'] ?? true) ? _e($item) : $item)
                    . "</li>\n";
            }

            $output .= "</ul>\n";
        }

        return $output;
    }

    /**
     * Render an exception
     */
    static function renderException(\Throwable $e, bool $showTrace = true, bool $showPrevious = true): string
    {
        return '<pre class="exception">' . _e(Exception::render($e, $showTrace, $showPrevious)) . "</pre>\n";
    }

    /**
     * Render HTML attributes
     *
     * @param array<string, scalar|null> $attrs
     * @return string HTML attribute string (including a space) or an empty string
     */
    static function renderAttrs(array $attrs): string
    {
        if (empty($attrs)) {
            return '';
        }

        $output = '';

        foreach ($attrs as $attr => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $output .= ' ' . $attr;

            if ($value !== true) {
                $output .= '="' . _e((string) $value) . '"';
            }
        }

        return $output;
    }

    /**
     * Render a script to limit a length of a textarea
     *
     * @param int $maxlength maximum length
     * @param string $form form name
     * @param string $name textarea name
     */
    static function jsLimitLength(int $maxlength, string $form, string $name): string
    {
        return <<<HTML
<script>
$(document).ready(function() {
    var events = ['keyup', 'mouseup', 'mousedown'];
    for (var i = 0; i < events.length; ++i) $(document)[events[i]](function() {
        Sunlight.limitTextarea(document.{$form}.{$name}, {$maxlength});
    });
});
</script>
HTML;
    }

    private static function renderTimestamp(string $settingVar, int $timestamp, ?string $type = null): string
    {
        $extend = Extend::buffer('render.timestamp', [
            'settings_var' => $settingVar,
            'timestamp' => $timestamp,
            'type' => $type,
        ]);

        if ($extend !== '') {
            return $extend;
        }

        return date(Settings::get($settingVar), $timestamp);
    }
}
