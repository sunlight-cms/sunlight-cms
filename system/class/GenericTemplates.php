<?php

namespace Sunlight;

use Sunlight\Util\UrlHelper;

abstract class GenericTemplates
{
    /**
     * Zformatovat ciselnout hodnotu na zaklade aktualni lokalizace
     *
     * @param number $number
     * @param int    $decimals
     * @return string
     */
    static function renderNumber($number, $decimals = 2)
    {
        if (is_int($number) || $decimals <= 0 || abs(fmod($number, 1)) < pow(0.1, $decimals)) {
            // an integer value
            return number_format($number, 0, '', _lang('numbers.thousands_sep'));
        } else {
            // a float value
            return number_format($number, $decimals, _lang('numbers.dec_point'), _lang('numbers.thousands_sep'));
        }
    }

    /**
     * Zformatovat timestamp na zaklade nastaveni systemu
     *
     * @param number      $timestamp UNIX timestamp
     * @param string|null $category  kategorie casu (null, article, post, activity)
     * @return string
     */
    static function renderTime($timestamp, $category = null)
    {
        $extend = Extend::buffer('time.format', array(
            'timestamp' => $timestamp,
            'category' => $category
        ));

        if ($extend !== '') {
            return $extend;
        } else {
            return date(_time_format, $timestamp);
        }
    }

    /**
     * Zformatovat velikost souboru
     *
     * @param int $bytes
     * @return string
     */
    static function renderFilesize($bytes)
    {
        $units = array('B', 'kB', 'MB');

        for ($i = 2; $i >= 0; --$i) {
            $bytesPerUnit = pow(1000, $i);
            $value = $bytes / $bytesPerUnit;
            if ($value >= 1 || $i === 0) {
                break;
            }
        }

        return static::renderNumber($i === 2 ? $value : ceil($value)) . ' ' . $units[$i];
    }

    /**
     * Zobrazit IP adresu
     *
     * @param string $ip ip adresa
     * @return string
     */
    static function renderIp($ip)
    {
        if (_user_group == 1) {
            // hlavni administratori vidi vzdy puvodni IP
            return $ip;
        }

        return hash_hmac('md5', $ip, Core::$secret);
    }

    /**
     * Sestavit zacatek HTML dokumentu
     *
     * @return string
     */
    static function renderHead()
    {
        $lang = _e(_lang('langcode.iso639'));
        $generator = _e('SunLight CMS ' . Core::VERSION);

        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
<meta charset="UTF-8">
<meta name="generator" content="{$generator}">

HTML;
    }

    /**
     * Sestavit HTML pro vlozeni CSS a JS do <head>
     *
     * Parametry v $assets:
     * ------------------------------------------------------
     * css          pole s cestami k css souborum
     * js           pole s cestami k js souborum
     * css_before   html vlozene pred css
     * css_after    html vlozene za css
     * js_before    html vlozene pred js
     * js_after     html vlozene za js
     * extend_event nazev extend udalosti pro toto sestaveni
     *
     * @param array $assets pole s konfiguraci assetu
     * @return string
     */
    static function renderHeadAssets(array $assets)
    {
        $html = '';
        $cacheParam = '_' . _cacheid;

        // vychozi hodnoty
        $assets += array(
            'meta' => '',
            'css' => array(),
            'js' => array(),
            'css_before' => '',
            'css_after' => '',
            'js_before' => '',
            'js_after' => '',
        );

        // extend udalost
        if (isset($assets['extend_event'])) {
            Extend::call($assets['extend_event'], array(
                'meta' => &$assets['meta'],
                'css' => &$assets['css'],
                'js' => &$assets['js'],
                'css_before' => &$assets['css_before'],
                'css_after' => &$assets['css_after'],
                'js_before' => &$assets['js_before'],
                'js_after' => &$assets['js_after'],
            ));
        }

        // meta
        $html .= $assets['meta'];

        // css
        $html .= $assets['css_before'];
        foreach ($assets['css'] as $item) {
            $html .= "\n<link rel=\"stylesheet\" href=\"" . UrlHelper::appendParams($item, $cacheParam) . "\" type=\"text/css\">";
        }
        $html .= $assets['css_after'];

        // javascript
        $html .= $assets['js_before'];
        foreach ($assets['js'] as $item) {
            $html .= "\n<script src=\"" . UrlHelper::appendParams($item, $cacheParam) . "\"></script>";
        }
        $html .= $assets['js_after'];

        return $html;
    }

    /**
     * Vykreslit seznam informaci
     *
     * Kazda polozka musi byt pole o 1 nebo 2 prvcich:
     *
     *      array(obsah) nebo array(popisek, obsah)
     *
     * @param array[] $infos
     * @param string  $class
     * @return string
     */
    static function renderInfos(array $infos, $class = 'list-info')
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
     * Sestavit kod pro limitovani delky textarey javascriptem
     *
     * @param int    $maxlength maximalni povolena delka textu
     * @param string $form      nazev formulare
     * @param string $name      nazev textarey
     * @return string
     */
    static function jsLimitLength($maxlength, $form, $name)
    {
        return <<<HTML
<script>
$(document).ready(function(){
    var events = ['keyup', 'mouseup', 'mousedown'];
    for (var i = 0; i < events.length; ++i) $(document)[events[i]](function() {
        Sunlight.limitTextarea(document.{$form}.{$name}, {$maxlength});
    });
});
</script>
HTML;
    }
}
