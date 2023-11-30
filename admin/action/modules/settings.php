<?php

use Sunlight\Admin\Admin;
use Sunlight\Admin\PageLister;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$saved = (bool) Request::get('saved');

// load settings
$settings = DB::queryRows('SELECT var,val FROM ' . DB::table('setting'), 'var', 'val');

// title type choices
$titletype_choices = [];

for ($x = 1; $x < 3; ++$x) {
    $titletype_choices[$x] = _lang('admin.settings.info.titletype.' . $x);
}

// admin scheme choices
$adminscheme_choices = [];

for ($x = 0; $x < 11; ++$x) {
    $adminscheme_choices[$x] = _lang('admin.settings.admin.adminscheme.' . $x);
}

// article rate mode choices
$ratemode_choices = [];

for ($x = 0; $x < 3; ++$x) {
    $ratemode_choices[$x] = _lang('admin.settings.articles.ratemode.' . $x);
}

// paging mode choices
$pagingmode_choices = [];

for ($x = 1; $x < 4; ++$x) {
    $pagingmode_choices[$x] = _lang('admin.settings.paging.pagingmode.' . $x);
}

// cron script URL
$cron_script_url = Router::path('system/script/cron.php', ['query' => ['key' => $settings['cron_auth']], 'absolute' => true]);

// define editable settings
$editable_settings = [
    'main' => [
        'items' => [
            ['name' => 'default_template', 'format' => 'text', 'choices' => Core::$pluginManager->choices('template')],
            ['name' => 'date_format', 'format' => 'html', 'input_class' => 'inputsmall'],
            ['name' => 'time_format', 'format' => 'html', 'input_class' => 'inputsmall'],
            ['name' => 'cacheid', 'format' => 'int', 'input_class' => 'inputsmall'],
            ['name' => 'language', 'format' => 'text', 'choices' => Core::$pluginManager->choices('language'), 'reload_on_update' => true],
            ['name' => 'language_allowcustom', 'format' => 'bool'],
            ['name' => 'notpublicsite', 'format' => 'bool'],
            ['name' => 'pretty_urls', 'format' => 'bool', 'force_install_check' => true],
        ],
    ],
    'info' => [
        'items' => [
            ['name' => 'title', 'format' => 'html'],
            ['name' => 'titletype', 'format' => 'int', 'choices' => $titletype_choices],
            ['name' => 'titleseparator', 'format' => 'html'],
            ['name' => 'description', 'format' => 'html'],
            ['name' => 'author', 'format' => 'html'],
            ['name' => 'favicon', 'format' => 'bool'],
        ],
    ],
    'admin' => [
        'items' => [
            ['name' => 'version_check', 'format' => 'bool'],
            ['name' => 'adminscheme', 'format' => 'int', 'choices' => $adminscheme_choices, 'reload_on_update' => true],
            ['name' => 'adminscheme_dark', 'format' => 'bool', 'reload_on_update' => true],
            ['name' => 'adminpagelist_mode', 'format' => 'text', 'choices' => [
                PageLister::MODE_FULL_TREE => mb_strtolower(_lang('admin.content.mode.tree')),
                PageLister::MODE_SINGLE_LEVEL => mb_strtolower(_lang('admin.content.mode.single')),
            ]],
        ],
    ],
    'users' => [
        'items' => [
            ['name' => 'registration', 'format' => 'bool'],
            ['name' => 'registration_confirm', 'format' => 'bool'],
            ['name' => 'registration_grouplist', 'format' => 'bool'],
            [
                'name' => 'defaultgroup',
                'format' => 'int',
                'table_id' => 'user_group',
                'input' => Admin::userSelect('defaultgroup', ['selected' => Settings::get('defaultgroup'), 'select_groups' => true]),
                'id' => false,
                'reload_on_update' => true,
            ],
            [
                'name' => 'rules',
                'format' => 'text',
                'help' => false,
                'extra_help' => _lang('admin.settings.users.rules.help'),
                'input' => Admin::editor('settings-rules', 'rules', _e($settings['rules']), ['rows' => 9, 'class' => 'areasmallwide']),
                'id' => false,
                'transform_back' => function (string $rules) {
                    return User::filterContent($rules, true, false);
                },
                'reload_on_update' => true,
            ],
            ['name' => 'password_min_len', 'format' => 'int', 'min_value' => 1, 'max_value' => Password::MAX_PASSWORD_LENGTH],
            ['name' => 'messages', 'format' => 'bool'],
            ['name' => 'lostpass', 'format' => 'bool'],
            ['name' => 'ulist', 'format' => 'bool'],
            ['name' => 'uploadavatar', 'format' => 'bool'],
            ['name' => 'show_avatars', 'format' => 'bool'],
        ],
    ],
    'emails' => [
        'items' => [
            ['name' => 'sysmail', 'format' => 'text'],
            ['name' => 'mailerusefrom', 'format' => 'bool'],
            ['name' => 'profileemail', 'format' => 'bool'],
            ['name' => 'atreplace', 'format' => 'html'],
        ],
    ],
    'articles' => [
        'items' => [
            ['name' => 'ratemode', 'format' => 'int', 'choices' => $ratemode_choices],
            ['name' => 'article_pic_w', 'format' => 'int', 'min_value' => 10],
            ['name' => 'article_pic_h', 'format' => 'int', 'min_value' => 10],
            ['name' => 'article_pic_thumb_w', 'format' => 'int', 'min_value' => 10],
            ['name' => 'article_pic_thumb_h', 'format' => 'int', 'min_value' => 10],
        ],
    ],
    'forum' => [
        'items' => [
            ['name' => 'topic_hot_ratio', 'format' => 'int', 'min_value' => 1],
        ],
    ],
    'galleries' => [
        'items' => [
            ['name' => 'galuploadresize_w', 'format' => 'int', 'min_value' => 10, 'max_value' => 10000],
            ['name' => 'galuploadresize_h', 'format' => 'int', 'min_value' => 10, 'max_value' => 10000],
            ['name' => 'galdefault_thumb_w', 'format' => 'int', 'min_value' => 10, 'max_value' => 1500],
            ['name' => 'galdefault_thumb_h', 'format' => 'int', 'min_value' => 10, 'max_value' => 1500],
            ['name' => 'galdefault_per_row', 'format' => 'int', 'min_value' => -1],
            ['name' => 'galdefault_per_page', 'format' => 'int', 'min_value' => 1],
        ],
    ],
    'functions' => [
        'items' => [
            ['name' => 'comments', 'format' => 'bool'],
            ['name' => 'search', 'format' => 'bool'],
            ['name' => 'fulltext_content_limit', 'format' => 'int', 'min_value' => 0, 'max_value' => DB::MAX_TEXT_LENGTH],
            ['name' => 'captcha', 'format' => 'bool'],
            ['name' => 'bbcode', 'format' => 'bool'],
        ],
    ],
    'paging' => [
        'items' => [
            ['name' => 'pagingmode', 'format' => 'int', 'choices' => $pagingmode_choices],
            ['name' => 'showpages', 'format' => 'int', 'transform_to' => function ($v) { return $v * 2 + 1; }, 'transform_back' => function ($v) { return (int) max(1, abs(($v - 1) / 2)); }],
            ['name' => 'commentsperpage', 'format' => 'int', 'min_value' => 1],
            ['name' => 'messagesperpage', 'format' => 'int', 'min_value' => 1],
            ['name' => 'articlesperpage', 'format' => 'int', 'min_value' => 1],
            ['name' => 'topicsperpage', 'format' => 'int', 'min_value' => 1],
            ['name' => 'extratopicslimit', 'format' => 'int', 'min_value' => 1],
            ['name' => 'sboxmemory', 'format' => 'int', 'min_value' => 1],
        ],
    ],
    'iplog' => [
        'items' => [
            ['name' => 'antispamtimeout', 'format' => 'int', 'min_value' => 0],
            ['name' => 'postadmintime', 'format' => 'int', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'maxloginattempts', 'format' => 'int', 'min_value' => 1],
            ['name' => 'maxloginexpire', 'format' => 'int', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'artviewexpire', 'format' => 'int', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'artrateexpire', 'format' => 'int', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'pollvoteexpire', 'format' => 'int', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'accactexpire', 'format' => 'int', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
            ['name' => 'lostpassexpire', 'format' => 'int', 'transform_to' => function ($v) { return $v / 60; }, 'transform_back' => function ($v) { return max(0, $v * 60); }],
        ],
    ],
    'filesystem' => [
        'items' => [
            [
                'name' => 'allowed_file_ext',
                'format' => 'text',
                'input' => '<textarea name="allowed_file_ext" class="areasmall" rows="9" cols="33">' . _e(Settings::get('allowed_file_ext')) . '</textarea>',
                'transform_back' => function (string $list) {
                    return implode(',', preg_split('{\s*+,\s*+}', $list, -1, PREG_SPLIT_NO_EMPTY));
                },
                'reload_on_update' => true,
            ],
        ],
    ],
    'cron' => [
        'items' => [
            ['name' => 'cron_auto', 'format' => 'bool'],
            ['name' => 'cron_auth', 'format' => 'text', 'help' => _lang('admin.settings.cron.cron_auth.help', ['%script_url%' => _e($cron_script_url)]), 'reload_on_update' => true],
            ['name' => 'maintenance_interval', 'format' => 'int'],
            ['name' => 'thumb_cleanup_threshold', 'format' => 'int'],
            ['name' => 'thumb_touch_threshold', 'format' => 'int'],
        ],
    ],
    'logger' => [
        'items' => [
            ['name' => 'log_level', 'format' => 'int', 'choices' => [-1 => _lang('admin.settings.logger.log_level.disabled')] + Logger::LEVEL_NAMES],
            ['name' => 'log_retention', 'format' => 'text', 'transform_back' => function ($v) { return ctype_digit($v) ? $v : ''; }],
        ],
    ],
];

// extend
Extend::call('admin.settings', [
    'editable' => &$editable_settings,
    'current' => &$settings,
]);

// save
if (!empty($_POST)) {
    $reload = false;
    $forceInstallCheck = false;

    foreach ($editable_settings as $settings_category_data) {
        foreach ($settings_category_data['items'] as $item) {
            if (!isset($settings[$item['name']])) {
                continue;
            }

            // load value
            if ($item['format'] === 'bool') {
                // checkbox
                $value = Form::loadCheckbox($item['name']) ? '1' : '0';
            } else {
                // value
                $value = trim(Request::post($item['name'], ''));

                switch ($item['format']) {
                    case 'int':
                        $value = (int) $value;
                        break;
                    case 'html':
                        $value = _e($value);
                        break;
                }

                // enforce minimum value
                if (isset($item['min_value']) && $value < $item['min_value']) {
                    $value = $item['min_value'];
                }

                // enforce maximum value
                if (isset($item['max_value']) && $value > $item['max_value']) {
                    $value = $item['max_value'];
                }

                // ID from a table
                if (
                    isset($item['table_id'])
                    && (!isset($item['empty_value']) || $value != $item['empty_value'])
                    && DB::count($item['table_id'], 'id=' . DB::val($value)) < 1
                ) {
                    // invalid ID
                    continue;
                }

                // choice
                if (isset($item['choices']) && !isset($item['choices'][$value])) {
                    // invalid choice
                    continue;
                }

                // transformation
                if (isset($item['transform_back'])) {
                    $value = $item['transform_back']($value);
                }
            }

            // update
            if ($value != $settings[$item['name']]) {
                Settings::update($item['name'], $value);
                $settings[$item['name']] = $value;

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
        $_admin->redirect(Router::admin('settings', ['query' => ['saved' => 1]]));
    }

    if ($forceInstallCheck) {
        Settings::update('install_check', '', false);
    }
}

// output
$output .= ($saved ? Message::ok(_lang('admin.settings.saved')) : '') . '

<form action="' . _e(Router::admin('settings')) . '" method="post">

<div id="settingsnav">
<input type="submit"  class="button bigger" value="' . _lang('global.savechanges') . '" accesskey="s">
<ul>
';

foreach ($editable_settings as $settings_category => $settings_category_data) {
    $title = $settings_category_data['title'] ?? _lang('admin.settings.' . $settings_category);

    $output .= '<li><a href="#settings_' . $settings_category . '">' . $title . "</a></li>\n";
}

$output .= '</ul>
</div>

<div id="settingsform">
';

foreach ($editable_settings as $settings_category => $settings_category_data) {
    $title = $settings_category_data['title'] ?? _lang('admin.settings.' . $settings_category);

    $output .= '<fieldset id="settings_' . $settings_category . '">
<legend>' . $title . '</legend>

<table>';

    foreach ($settings_category_data['items'] as $item) {
        if (!isset($settings[$item['name']])) {
            continue;
        }

        $id = "setting_{$item['name']}";
        $value = $settings[$item['name']];

        // transformation
        if (isset($item['transform_to'])) {
            $value = $item['transform_to']($value);
        }

        // label
        $label = $item['label'] ?? _lang('admin.settings.' . $settings_category . '.' . $item['name']);

        // input
        if (!isset($item['input'])) {
            // attributes
            $inputAttrs = ' name="' . $item['name'] . '"';

            if (!isset($item['id']) || $item['id']) {
                $inputAttrs .= ' id="' . $id . '"';
            }

            if (isset($item['disabled']) && $item['disabled']) {
                $inputAttrs .= ' disabled="disabled"';
            }

            if ($item['format'] !== 'bool') {
                if (!isset($item['input_class'])) {
                    if (!isset($item['choices'])) {
                        $inputAttrs .= ' class="inputmedium"';
                    }
                } else {
                    $inputAttrs .= ' class="' . $item['input_class'] . '"';
                }
            }

            // input
            if (isset($item['choices'])) {
                $input = "<select{$inputAttrs}>\n";

                foreach ($item['choices'] as $choiceValue => $choiceLabel) {
                    $input .= '<option' . Form::selectOption($choiceValue == $value) . ' value="' . _e($choiceValue) . '">' . $choiceLabel . "</option>\n";
                }

                $input .= '</select>';
            } else {
                switch ($item['format']) {
                    case 'int':
                        $input = '<input type="number"' . $inputAttrs . ' value="' . _e($value) . '">';
                        break;
                    case 'bool':
                        $input = '<input type="checkbox"' . $inputAttrs . ' value="1"' . Form::activateCheckbox($value) . '>';
                        break;
                    case 'html':
                    default:
                        $input = '<input type="text"' . $inputAttrs . ' value="'
                            . ($item['format'] === 'html' ? $value : _e($value))
                            . '">';
                        break;
                }
            }
        }  else {
            $input = $item['input'];
        }

        // help
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

        // item
        $output .= '<tr>
    <td><label' . (!isset($item['id']) || $item['id'] ? ' for="' . $id . '"' : '') . ">{$label}</label></td>
    <td" . ($help === '' ? ' colspan="2"' : '') . ">{$input}</td>\n";

        if ($help !== '') {
            $output .= "<td>{$help}</td>\n";
        }

        $output .= "</tr>\n";

        // extra help
        if (isset($item['extra_help'])) {
            $output .= '<tr>
    <td></td>
    <td colspan="2"><p>' . $item['extra_help'] . "</p></td>
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
