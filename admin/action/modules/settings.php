<?php

use Sunlight\Admin\Admin;
use Sunlight\Admin\PageLister;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Plugin\PluginManager;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* --- priprava --- */

$saved = (bool) Request::get('saved');

// nacteni nastaveni
$settings = [];
$query = DB::query('SELECT var,val,format FROM ' . _setting_table);
while ($row = DB::row($query)) {
    $settings[$row['var']] = $row;
}

// vyber zpusobu zobrazeni titulku
$titletype_choices = [];
for ($x = 1; $x < 3; ++$x) {
    $titletype_choices[$x] = _lang('admin.settings.info.titletype.' . $x);
}

// vyber schematu administrace
$adminscheme_choices = [];
for ($x = 0; $x < 11; ++$x) {
    $adminscheme_choices[$x] = _lang('admin.settings.admin.adminscheme.' . $x);
}

// vyber modu schematu administrace
$adminscheme_mode_choices = [];
for ($x = 0; $x < 3; ++$x) {
    $adminscheme_mode_choices[$x] = _lang('admin.settings.admin.adminscheme_mode.' . $x);
}

// vyber zpusobu hodnoceni clanku
$ratemode_choices = [];
for ($x = 0; $x < 3; ++$x) {
    $ratemode_choices[$x] = _lang('admin.settings.articles.ratemode.' . $x);
}

// vyber zobrazeni strankovani
$pagingmode_choices = [];
for ($x = 1; $x < 4; ++$x) {
    $pagingmode_choices[$x] = _lang('admin.settings.paging.pagingmode.' . $x);
}

// konfigurace editovatelnych direktiv
$editable_settings = [
    'main' => [
        'items' => [
            ['name' => 'default_template', 'choices' => Core::$pluginManager->choices(PluginManager::TEMPLATE)],
            ['name' => 'time_format', 'input_class' => 'inputsmall'],
            ['name' => 'cacheid', 'input_class' => 'inputsmall'],
            ['name' => 'language', 'choices' => Core::$pluginManager->choices(PluginManager::LANGUAGE), 'reload_on_update' => true],
            ['name' => 'language_allowcustom'],
            ['name' => 'notpublicsite'],
            ['name' => 'proxy_mode', 'help_attrs' => ['%ip%' => _user_ip, '%link%' => 'https://sunlight-cms.cz/resource/ip-compare?with=' . rawurlencode(_user_ip)]],
            ['name' => 'pretty_urls', 'force_install_check' => true],
        ],
    ],
    'info' => [
        'items' => [
            ['name' => 'title'],
            ['name' => 'titletype', 'choices' => $titletype_choices],
            ['name' => 'titleseparator'],
            ['name' => 'description'],
            ['name' => 'author'],
            ['name' => 'favicon'],
        ],
    ],
    'admin' => [
        'items' => [
            ['name' => 'adminlinkprivate'],
            ['name' => 'version_check'],
            ['name' => 'adminscheme', 'choices' => $adminscheme_choices, 'reload_on_update' => true],
            ['name' => 'adminscheme_mode', 'choices' => $adminscheme_mode_choices, 'reload_on_update' => true],
            ['name' => 'adminpagelist_mode', 'choices' => [
                PageLister::MODE_FULL_TREE => mb_strtolower(_lang('admin.content.mode.tree')),
                PageLister::MODE_SINGLE_LEVEL => mb_strtolower(_lang('admin.content.mode.single')),
            ]],
        ],
    ],
    'users' => [
        'items' => [
            ['name' => 'registration'],
            ['name' => 'registration_confirm'],
            ['name' => 'registration_grouplist'],
            ['name' => 'defaultgroup', 'table_id' => _user_group_table, 'input' => Admin::userSelect("defaultgroup", Settings::get('defaultgroup'), "id!=" . User::GUEST_GROUP_ID, null, null, true), 'id' => false],
            ['name' => 'rules', 'help' => false, 'extra_help' => _lang('admin.settings.users.rules.help'), 'input' => '<textarea id="setting_rules" name="rules" rows="9" cols="33" class="areasmallwide editor">' . _e($settings['rules']['val']) . '</textarea>'],
            ['name' => 'messages'],
            ['name' => 'lostpass'],
            ['name' => 'ulist'],
            ['name' => 'uploadavatar'],
            ['name' => 'show_avatars'],
        ],
    ],
    'emails' => [
        'items' => [
            ['name' => 'sysmail'],
            ['name' => 'mailerusefrom'],
            ['name' => 'profileemail'],
            ['name' => 'atreplace'],

        ],
    ],
    'articles' => [
        'items' => [
            ['name' => 'ratemode', 'choices' => $ratemode_choices],
            ['name' => 'article_pic_w', 'min_value' => 10],
            ['name' => 'article_pic_h', 'min_value' => 10],
            ['name' => 'article_pic_thumb_w', 'min_value' => 10],
            ['name' => 'article_pic_thumb_h', 'min_value' => 10],
        ],
    ],
    'forum' => [
        'items' => [
            ['name' => 'topic_hot_ratio', 'min_value' => 1],
        ],
    ],
    'galleries' => [
        'items' => [
            ['name' => 'galuploadresize_w', 'min_value' => 10, 'max_value' => 1024],
            ['name' => 'galuploadresize_h', 'min_value' => 10, 'max_value' => 1024],
            ['name' => 'galdefault_thumb_w', 'min_value' => 10, 'max_value' => 1024],
            ['name' => 'galdefault_thumb_h', 'min_value' => 10, 'max_value' => 1024],
            ['name' => 'galdefault_per_row', 'min_value' => -1],
            ['name' => 'galdefault_per_page', 'min_value' => 1],
        ],
    ],
    'functions' => [
        'items' => [
            ['name' => 'comments'],
            ['name' => 'search'],
            ['name' => 'captcha'],
            ['name' => 'bbcode'],
        ],
    ],
    'paging' => [
        'items' => [
            ['name' => 'pagingmode', 'choices' => $pagingmode_choices],
            ['name' => 'showpages', 'transform_to' => function ($v) { return $v * 2 + 1; }, 'transform_back' => function ($v) { return (int) max(1, abs(($v - 1) / 2)); }],
            ['name' => 'commentsperpage', 'min_value' => 1],
            ['name' => 'messagesperpage', 'min_value' => 1],
            ['name' => 'articlesperpage', 'min_value' => 1],
            ['name' => 'topicsperpage', 'min_value' => 1],
            ['name' => 'extratopicslimit', 'min_value' => 1],
            ['name' => 'sboxmemory', 'min_value' => 1],
        ],
    ],
    'iplog' => [
        'items' => [
            ['name' => 'antispamtimeout', 'min_value' => 0],
            ['name' => 'postadmintime', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'maxloginattempts', 'min_value' => 1],
            ['name' => 'maxloginexpire', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'artreadexpire', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'artrateexpire', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'pollvoteexpire', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'accactexpire', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'lostpassexpire', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
        ],
    ],
    'cron' => [
        'items' => [
            ['name' => 'cron_auto'],
            ['name' => 'cron_auth'],
            ['name' => 'maintenance_interval'],
            ['name' => 'thumb_cleanup_threshold'],
            ['name' => 'thumb_touch_threshold'],
        ],
    ],
];

// extend
Extend::call('admin.settings', [
    'editable' => &$editable_settings,
    'current' => &$settings,
]);

/* ---  ulozeni  --- */

if (!empty($_POST)) {

    $reload = false;
    $forceInstallCheck = false;

    foreach ($editable_settings as $settings_category_data) {
        foreach ($settings_category_data['items'] as $item) {

            if (!isset($settings[$item['name']])) {
                continue;
            }

            // nacist odeslanou hodnotu
            if ($settings[$item['name']]['format'] === 'bool') {
                // checkbox
                $value = Form::loadCheckbox($item['name']) ? '1' : '0';
            } else {
                // hodnota
                $value = trim(Request::post($item['name'], ''));
                switch ($settings[$item['name']]['format']) {
                    case 'int':
                        $value = (int) $value;
                        break;
                    case 'html':
                        $value = _e($value);
                        break;
                }

                // minimalni hodnota
                if (isset($item['min_value']) && $value < $item['min_value']) {
                    $value = $item['min_value'];
                }

                // maximalni hodnota
                if (isset($item['max_value']) && $value > $item['max_value']) {
                    $value = $item['max_value'];
                }

                // ID z tabulky
                if (
                    isset($item['table_id'])
                    && (!isset($item['empty_value']) || $value != $item['empty_value'])
                    && DB::count($item['table_id'], 'id=' . DB::val($value)) < 1
                ) {
                    // neplatne ID
                    continue;
                }

                // volba
                if (isset($item['choices']) && !isset($item['choices'][$value])) {
                    // neplatna volba
                    continue;
                }

                // transformace
                if (isset($item['transform_back'])) {
                    $value = $item['transform_back']($value);
                }
            }

            // update
            if ($value != $settings[$item['name']]['val']) {
                Settings::update($item['name'], $value);
                $settings[$item['name']]['val'] = $value;

                if (isset($item['reload_on_update']) && $item['reload_on_update']) {
                    $reload = true;
                }
                if (isset($item['force_install_check']) && $item['force_install_check']) {
                    $reload = true;
                    $forceInstallCheck = true;
                }
            }

        }
    }

    $saved = true;
    if ($reload) {
        $admin_redirect_to = 'index.php?p=settings&saved';
    }
    if ($forceInstallCheck) {
        Settings::update('install_check', '1');
    }
}

/* ---  vystup  --- */

$output .= ($saved ? Message::ok(_lang('admin.settings.saved')) : '') . '

<form action="index.php?p=settings" method="post">

<div id="settingsnav">
<input type="submit"  class="button bigger" value="' . _lang('global.savechanges') . '" accesskey="s">
<ul>
';

foreach ($editable_settings as $settings_category => $settings_category_data) {
    $title = $settings_category_data['title'] ?? _lang('admin.settings.' . $settings_category);

    $output .= "<li><a href=\"#settings_{$settings_category}\">{$title}</a></li>\n";
}

$output .= '</ul>
</div>

<div id="settingsform">
';

foreach ($editable_settings as $settings_category => $settings_category_data) {
    $title = $settings_category_data['title'] ?? _lang('admin.settings.' . $settings_category);

    $output .= "<fieldset id=\"settings_{$settings_category}\">
<legend>{$title}</legend>

<table>";

    foreach ($settings_category_data['items'] as $item) {
        if (!isset($settings[$item['name']])) {
            continue;
        }

        $id = "setting_{$item['name']}";
        $value = $settings[$item['name']]['val'];

        // transformace
        if (isset($item['transform_to'])) {
            $value = $item['transform_to']($value);
        }

        // popisek
        $label = $item['label'] ?? _lang('admin.settings.' . $settings_category . '.' . $item['name']);

        // input
        if (!isset($item['input'])) {
            // atributy
            $inputAttrs = " name=\"{$item['name']}\"";
            if (!isset($item['id']) || $item['id']) {
                $inputAttrs .= " id=\"{$id}\"";
            }
            if (isset($item['disabled']) && $item['disabled']) {
                $inputAttrs .= " disabled=\"disabled\"";
            }
            if ($settings[$item['name']]['format'] !== 'bool') {
                if (!isset($item['input_class'])) {
                    if (!isset($item['choices'])) {
                        $inputAttrs .= " class=\"inputmedium\"";
                    }
                } else {
                    $inputAttrs .= " class=\"{$item['input_class']}\"";
                }
            }

            // input
            if (isset($item['choices'])) {
                $input = "<select{$inputAttrs}>\n";
                foreach ($item['choices'] as $choiceValue => $choiceLabel) {
                    $input .= "<option" . ($choiceValue == $value ? ' selected' : '') . " value=\"" . _e($choiceValue) . "\">{$choiceLabel}</option>\n";
                }
                $input .= "</select>";
            } else {
                switch ($settings[$item['name']]['format']) {
                    case 'int':
                        $input = "<input type=\"number\"{$inputAttrs} value=\"" . _e($value) . "\">";
                        break;
                    case 'bool':
                        $input = "<input type=\"checkbox\"{$inputAttrs} value=\"1\"" . Form::activateCheckbox($value) . ">";
                        break;
                    case 'html':
                    default:
                        $input = "<input type=\"text\"{$inputAttrs} value=\""
                            . ($settings[$item['name']]['format'] === 'html' ? $value : _e($value))
                            . "\">";
                        break;
                }
            }
        }  else {
            $input = $item['input'];
        }

        // napoveda
        if (isset($item['help'])) {
            if ($item['help'] !== false) {
                $help = $item['help'];
            } else {
                $help = '';
            }
        } else {
            $help = _lang('admin.settings.' . $settings_category . '.' . $item['name'] . '.help');
        }
        if (isset($item['help_attrs'])) {
            $help = strtr($help, $item['help_attrs']);
        }

        // polozka
        $output .= "<tr>
    <td><label" . (!isset($item['id']) || $item['id'] ? " for=\"{$id}\"" : '') . ">{$label}</label></td>
    <td" . ($help === '' ? ' colspan="2"' : '') . ">{$input}</td>\n";
        if ($help !== '') {
            $output .= "<td>{$help}</td>\n";
        }
        $output .= "</tr>\n";

        // extra napoveda
        if (isset($item['extra_help'])) {
            $output .= "<tr>
    <td></td>
    <td colspan=\"2\"><p>{$item['extra_help']}</p></td>
</tr>\n";

        $output .= "\n";
        }

    }

    $output .= "</table>\n</fieldset>\n\n";
}

$output .= '
</div>

' . Xsrf::getInput() . '</form>

<script>
(function () {
    $("#settingsnav").scrollFix({
        style: false,
        topPosition: 10
    });

    $("fieldset[id]").scrollWatchMapTo("#settingsnav li", null, {
        resolutionMode: "focus-line",
        focusRatio: 0,
        focusOffset: 50
    });
})();
</script>

';
