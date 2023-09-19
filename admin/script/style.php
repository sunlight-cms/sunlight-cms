<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Util\Color;
use Sunlight\Util\DateTime;
use Sunlight\Util\Request;

require __DIR__ . '/../../system/bootstrap.php';
Core::init([
    'env' => Core::ENV_ADMIN,
    'session_enabled' => false,
    'content_type' => 'text/css; charset=UTF-8',
]);

header('Expires: ' . DateTime::formatForHttp(2592000, true));

$dark = isset($_GET['d']);
$s = (int) Request::get('s', 0);

$adminColor = function(int $loff = 0, ?float $satc = null, bool $sat_abs = false, bool $light_abs = false): string {
    if ($satc === 0.0) {
        $light_abs = true;
        $loff += 127;
    }

    $h = $GLOBALS['hue'];

    if ($GLOBALS['dark']) {
        $l = ($light_abs ? 255 - $loff : $GLOBALS['light'] - $loff);
    } else {
        $l = ($light_abs ? $loff : $GLOBALS['light'] + $loff);
    }

    $s = (isset($satc) ? ($sat_abs ? $satc :  $GLOBALS['sat'] * $satc) : $GLOBALS['sat']);

    return (new Color([$h, $s, $l], 1))->getRgbStr();
};

// default HSL values
$hue = 0;
$light = 127;
$sat = 255;

// default color values
$scheme_link = null;
$scheme_bar_text = null;
$scheme_bar_shadow = null;
$scheme_bar_flip = false;

if ($dark) {
    $scheme_white = '#000';
    $scheme_black = '#fff';
    $scheme_bg_info = '#00626A';
    $scheme_bg_alert = '#845100';
    $scheme_bg_danger = '#840000';
} else {
    $scheme_white = '#fff';
    $scheme_black = '#000';
    $scheme_bg_info = '#D0EDEE';
    $scheme_bg_alert = '#FFD183';
    $scheme_bg_danger = '#FFA7A7';
}

$scheme_bar_loff = 30;
$scheme_text = $scheme_black;

if ($dark) {
    $scheme_contrast = $scheme_black;
    $scheme_contrast2 = $scheme_white;
} else {
    $scheme_contrast = $scheme_white;
    $scheme_contrast2 = $scheme_black;
}

$scheme_link_loff = ($dark ? -20 : -10);
$dark_suffix = ($dark ? '_dark' : '');

// apply scheme
switch ($s) {
    // blue
    case 1:
        $hue = 145;
        $sat -= 10;
        break;

    // green
    case 2:
        $hue = 70;

        if (!$dark) {
            $light -= 20;
        }

        $sat *= 0.7;
        break;

    // red
    case 3:
        $hue = 5;

        if (!$dark) {
            $light -= 10;
        }
        break;

    // yellow
    case 4:
        $hue = 35;
        $scheme_contrast = $scheme_black;
        $scheme_link = '#BE9B02';

        if (!$dark) {
            $light -= 20;
            $scheme_bar_flip = true;
        } else {
            $light += 5;
        }
        break;

    // purple
    case 5:
        $hue = 205;
        break;

    // azure
    case 6:
        $hue = 128;

        if (!$dark) {
            $light -= 10;
            $sat -= 70;
            $scheme_link_loff -= 10;
            $scheme_bar_flip = true;
        }
        break;

    // violet
    case 7:
        $hue = 195;

        if ($dark) {
            $light += 10;
        }
        break;

    // brown
    case 8:
        $hue = 20;
        $light -= 10;
        $sat *= 0.6;
        break;

    // dark blue
    case 9:
        $hue = 170;

        if ($dark) {
            $scheme_link_loff -= 20;
        }

        $sat *= 0.5;
        break;

    // grey
    case 10:
        $hue = 150;
        $sat = 0;
        $scheme_link = '#67939F';
        $scheme_bar_loff = 50;

        if (!$dark) {
            $scheme_bar_flip = true;
        }
        break;

    // orange
    default:
        $hue = 14;
        $scheme_link = '#F84A00';
        $light -= 10;
        break;
}

Extend::call('admin.style.init');

// generate colors
$scheme = $adminColor(($dark ? 40 : 0));
$scheme_lighter = $adminColor(80);
$scheme_lightest = $adminColor(100);
$scheme_smoke = $adminColor(115, 0);
$scheme_smoke_text = $adminColor((int) ($light * 0.2), 0);
$scheme_smoke_text_dark = $adminColor(10, 0);
$scheme_smoke_text_darker = $adminColor(-30, 0);
$scheme_smoke = $adminColor(110, 0);
$scheme_smoke_med = $adminColor(90, 0);
$scheme_smoke_dark = $adminColor(60, 0);
$scheme_smoke_darker = $adminColor($dark ? -20 : -10, 0);
$scheme_smoke_light = $adminColor(110, 0);
$scheme_smoke_lighter = $adminColor(118, 0);
$scheme_smoke_lightest = $adminColor(125, 0);
$scheme_smoke_lightest_colored = $adminColor(125);
$scheme_med = $adminColor(30);
$scheme_dark = $adminColor(-10);
$scheme_bar = $adminColor($scheme_bar_loff);

if ($scheme_link == null) {
    $scheme_link = $adminColor($scheme_link_loff, 255, true);
}

if ($scheme_bar_shadow === null) {
    $scheme_bar_shadow = ($scheme_bar_flip ? 'rgba(255, 255, 255, 0.3)' : 'rgba(0, 0, 0, 0.3)');
}

if ($dark) {
    $scheme_bar_flip = !$scheme_bar_flip;
}

if ($scheme_bar_text === null) {
    $scheme_bar_text = ($scheme_bar_flip ? $scheme_black : $scheme_white);
}

if ($dark) {
    $scheme_alpha_shadow = 'rgba(255, 255, 255, 0.15)';
    $scheme_alpha_shadow2 = 'rgba(255, 255, 255, 0.075)';
} else {
    $scheme_alpha_shadow = 'rgba(0, 0, 0, 0.15)';
    $scheme_alpha_shadow2 = 'rgba(0, 0, 0, 0.075)';
}

Extend::call('admin.style.start');

?>
/* <style>
/* tags */
* {margin: 0; padding: 0;}
body {font-family: sans-serif; font-size: 12px; color: <?= $scheme_text ?>; background-color: <?= $scheme_smoke_light ?>; margin: 0 0 1em 0;}
a {font-size: 12px; color: <?= $scheme_link ?>; text-decoration: none;}
a:hover {color: <?= $scheme_text ?>; text-decoration: none;}
h1 {font-size: 18px; font-weight: normal; margin-bottom: 0.5em;}
h2 {font-size: 14px; margin: 0.25em 0;}
h3 {font-size: 12px; margin: 0.25em 0;}
p {padding: 0; margin: 2px 0 10px 0; line-height: 160%;}
ul, ol {padding: 2px 0 12px 40px; line-height: 160%;}
dfn {font-style: normal; border-bottom: 1px dashed <?= $scheme_smoke_dark ?>; cursor: help;}
img {border: 0;}
small {color: <?= $scheme_smoke_text ?>;}
td, th {font-size: 12px; padding: 1px;}
th {text-align: left; font-weight: bold;}
form td {padding: 2px;}
form > table > tbody > tr > th, .cform th {padding-right: 10px; text-align: right;}

/* forms */
form {margin: 0 0 8px 0;}
form.inline {line-height: 1;}
fieldset {margin: 25px 0; padding: 8px; background-color: <?= $scheme_smoke_lighter ?>; border: 1px solid <?= $scheme_smoke ?>;}
fieldset fieldset {background-color: <?= $scheme_white ?>;}
fieldset.hs_fieldset > legend:hover {text-decoration: underline;}
legend {font-weight: bold; color: <?= $scheme_text ?>;}
label {padding-right: 0.5em;}

input, button, select, textarea {padding: 6px; margin: 0; box-sizing: border-box; font-size: 12px; line-height: 1 !important; vertical-align: middle;}

input[type=checkbox], input[type=radio] {padding: 0; margin: 3px; border: none; vertical-align: middle;}
input[type=text], input[type=password], input[type=number], input[type=email], input[type=search], input[type=datetime-local], select {height: 26px; border: 1px solid <?= $scheme_smoke_dark ?>; box-shadow: inset 0 0 4px <?= $scheme_alpha_shadow2 ?>;}
input[type=submit], input[type=button], input[type=reset], button {cursor: pointer; padding: 8px 12px; border: 1px solid <?= $scheme_smoke_med ?>; background: <?= $scheme_smoke_lighter ?>; background: linear-gradient(to bottom, <?= $scheme_smoke_lightest ?>, <?= $scheme_smoke ?>); color: <?= $scheme_text ?>; font-size: 13px;}
input[type=submit]:focus, input[type=button]:focus, input[type=reset]:focus, button:focus {outline: 1px solid <?= $scheme_med ?>;}
input[type=submit]:hover, input[type=button]:hover, input[type=reset]:hover, button:hover {background: <?= $scheme_lightest ?>; background: linear-gradient(to bottom, <?= $scheme_lightest ?>, <?= $scheme_lighter ?>); border-color: <?= $scheme_lighter ?>; outline: none;}
input[type=color] {padding: 0;}

select {padding: 0 20px 0 6px; background: <?= $scheme_white ?> url("../public/images/select_arrow<?= $dark ? '_inverted' : '' ?>.png") right center no-repeat; appearance: none; -webkit-appearance: none; -moz-appearance: none; text-indent: 0.01px; text-overflow: ''; line-height: 2 !important;}
select::-ms-expand {display: none;}
select[multiple], select[size] {height: auto;}
optgroup option {padding-left: 16px;}

@-moz-document url-prefix() {
    input::-moz-focus-inner, button::-moz-focus-inner, select::-moz-focus-inner, textarea::-moz-focus-inner {border: 0; padding: 0;}
    input[type=text], input[type=password], select {padding-top: 0; padding-bottom: 0;}
}

<?php if ($dark) { ?>
input, textarea, button, select {
    background-color: <?= $scheme_white ?>;
    color: <?= $scheme_black ?>;
    border: 1px solid <?= $scheme_smoke_dark ?>;
}
<?php } ?>

/* layout */
.wrapper {max-width: 1400px; min-width: 700px; margin: 0 auto;}

/* header */
#top {background: url("../public/images/top_bg<?= $dark_suffix ?>.png") left bottom repeat-x;}
#header {font-family: Georgia, "Times New Roman", Times, serif; font-size: 24px; padding: 0.7em 16px 0.5em 16px;}
#title {color: <?= $scheme_smoke_darker ?>;}
#usermenu {float: right; position: relative; top: 6px;}
#usermenu, #usermenu a {font-size: 14px; color: <?= $scheme_smoke_text ?>;}
#usermenu a {text-decoration: none;}
#usermenu a:hover {color: <?= $scheme_link ?>;}
a#usermenu-username {margin-right: 0.5em; font-weight: bold; color: <?= $scheme_smoke_darker ?>;}
#usermenu a.usermenu-web-link {margin-left: 0.5em;}
#usermenu-avatar {position: absolute; left: -42px; top: -8px; display: block; width: 32px; height: 32px; overflow: hidden; border: 1px solid <?= $scheme_smoke_med ?>; background-color: <?= $scheme_white ?>; vertical-align: middle; border-radius: 50%;}
#usermenu-avatar img {max-width: 32px; opacity: 0.9;}
#usermenu-avatar:hover img {opacity: 1;}

/* menu */
#menu {position: relative; padding: 5px 0 0 16px; background-color: <?= $scheme_bar ?>; font-size: 0;}
#menu a {color: <?= $scheme_bar_text ?>; text-decoration: none; display: inline-block;}
#menu a span {display: inline-block; padding: 7px 16px; background: url("../public/images/menu_sep<?= $dark_suffix ?>.png") right bottom no-repeat; font-size: 13px; text-shadow: 0 0 5px <?= $scheme_bar_shadow ?>;}
#menu a:hover span, #menu a.act span {color: <?= $scheme_black ?>; background: <?= $scheme_white ?> url("../public/images/menu_active<?= $dark_suffix ?>.png") left top repeat-x; text-shadow: none;}

/* content */
#content {padding: 12px 16px 16px 16px; background-color: <?= $scheme_white ?>;}

/* copyright / footer */
#footer {margin-bottom: 0.5em; text-align: right; padding: 8px 16px; background-color: <?= $scheme_bar ?>;}
#footer, #footer a {font-size: 11px; text-decoration: none; color: <?= $scheme_bar_text ?>; text-shadow: 0 0 5px <?= $scheme_bar_shadow ?>;}
#footer a:hover {text-decoration: underline;}
#footer-links {float: left;}
#footer-links a {margin-right: 1em;}

/* login layout */
.login-layout {background: radial-gradient(at center 270px, <?= $scheme_smoke_light ?>, <?= $scheme_smoke_dark ?>) no-repeat fixed;}
.login-layout .wrapper {width: 500px; min-width: 0;}
.login-layout #header, .login-layout #menu {display: none;}
.login-layout #top {padding-top: 140px; background: url("../public/images/logo.png") center 50px no-repeat;}
.login-layout #content {padding: 24px 16px; box-shadow: 0 0 6px 1px <?= $scheme_alpha_shadow ?>; text-align: center;}
.login-layout #content form {display: inline-block;}
.login-layout .login-form-links, .login-layout #content form {text-align: left;}
.login-layout .login-form-links {padding: 0 0 0 3px; list-style-type: none;}
.login-layout #content .message {text-align: left;}
.login-layout #footer {background: none;}
.login-layout #footer, .login-layout #footer a {color: <?= $scheme_text ?>; text-shadow: none;}

/* external container */
#external-container {padding: 10px;}
#external-container h1 {border-bottom: 3px solid <?= $scheme ?>; padding-bottom: 3px; margin-bottom: 6px;}

/* index */
#index-table {width: 100%; margin: 0; padding: 0; border-collapse: collapse;}
#index-table > tbody > tr > td {padding: 10px; border: 1px solid <?= $scheme_smoke_med ?>; background-color: <?= $scheme_smoke_lighter ?>;}
#index-table > tbody > tr > td:last-child {width: 200px;}
#index-table h2 {margin-bottom: 6px; border-bottom: 2px solid <?= $scheme_smoke_med ?>; padding-bottom: 6px;}
#index-table li {padding: 3px;}
#index-table table {width: 100%;}
#index-table table th, #index-table table td {text-align: left; padding: 0.2em; border-bottom: 1px solid <?= $scheme_smoke_med ?>;}
#index-table table tr:last-child th, #index-table table tr:last-child td {border-bottom: none;}
.module-index .latest-version {font-weight: bold; color: #0077a7;}
.module-index .latest-version.latest-version-age-0 {color: #009800;}
.module-index .latest-version.latest-version-age-1 {color: #cda869;}
.module-index .latest-version.latest-version-age-2 {color: #f06c00;}
.module-index .latest-version.latest-version-age-3 {color: #e60000;}

/* content management */
#contenttable {width: 100%; border: 1px solid <?= $scheme_smoke ?>; line-height: 140%;}
#contenttable a {text-decoration: none;}
#contenttable h2 {margin: 0 0 8px 0; padding: 4px 0 7px 0; border-bottom: 1px solid <?= $scheme_smoke ?>;}
#contenttable .pad {padding: 20px 0;}
.contenttable-box {padding: 8px; margin: 0; border-right: 1px solid <?= $scheme_smoke ?>;}
.contenttable-box.main-box {width: 70%; padding-bottom: 0px;}
.customsettings {padding-left: 10px;}

#content-modules {border: none;}
#content-modules h2 {margin-top: 1em;}
#content-modules h2:first-child {margin-top: 0;}

/* page list */
.page-list {width: 100%; margin-bottom: 0.5em; border-collapse: separate; border-spacing: 0; white-space: nowrap;}
.page-list td {min-width: 19px; padding: 0 5px; border-left: 1px solid <?= $scheme_white ?>; line-height: 30px; position: relative;}
.page-list td:first-child {border-left: none;}

.page-list a {color: <?= $scheme_text ?>;}

.page-list .page-title {width: 90%;}
.page-list .page-title a, .page-list .page-title span {display: block;}
.page-list.page-list-full-tree .page-title > :not(.node-level-p0) .page-list-title {padding-left: 8px; border-left: 1px solid <?= $scheme_smoke_dark ?>;}
.page-list.page-list-full-tree .page-separator .page-title .page-list-title {border-left-color: <?= $scheme_smoke_text ?>;}
.page-list.page-list-full-tree .page-title a:hover span:after, .page-list.page-list-single-level td.page-title a span:after {content: url("../public/images/down<?= $dark ? '_inverted' : '' ?>.png"); position: absolute; margin-left: 0.5em; margin-top: 2px;}

.page-list tr:hover:not(.page-separator) td {background-color: <?= $scheme_lighter ?>;}

.page-list .page-separator td {border-top: 24px solid <?= $scheme_white ?>; border-bottom: 2px solid <?= $scheme_smoke_dark ?>; background-color: <?= $scheme_lighter ?>;}
.page-list .page-separator.sorting td {border-top: none;}
.page-list .page-separator:first-child td {border: none;}
.page-list .page-separator .page-title .page-list-title span {font-weight: bold;}

.page-list .page-type {color: <?= $scheme_smoke_text_dark ?>;}
.page-list tr:hover .page-type {color: inherit;}

.page-list .page-actions {text-align: right; white-space: nowrap;}
.page-list .page-actions a {display: inline; padding: 2px 6px;}
.page-list tr:hover .page-actions a {background-color: <?= $scheme_smoke ?>; outline: 1px solid <?= $scheme_smoke_dark ?>;}
.page-list .page-actions a img.icon {margin: 0; padding: 0; vertical-align: middle;}
.page-list .page-actions a span {display: none;}

.page-list .sortable-placeholder td {height: 36px;}
.page-list .page-list-sortcell {width: 1%; vertical-align: bottom;}

.page-list-breadcrumbs {margin: 0 0 0.5em 0; padding: 0.5em 1em; border-bottom: 2px solid <?= $scheme_smoke_dark ?>; list-style-type: none; background-color: <?= $scheme_lightest ?>;}
.page-list-breadcrumbs li {display: inline; padding-right: 0.5em;}
.page-list-breadcrumbs li:not(:last-child):after {content: ">"; display: inline-block; margin-left: 0.5em;}
.page-list-breadcrumbs a {color: <?= $scheme_text ?>; font-weight: bold; vertical-align: middle;}
.page-list-breadcrumbs a:hover {color: <?= $scheme_link ?>;}

/* editpages settings */
#settingseditform fieldset label{display: block;}
#settingseditform fieldset:first-child {margin: 0 0 25px 0;}
#settingseditform table td { padding: 4px 8px;border-bottom: 1px solid #d9d9d9;}
#settingseditform table tr:last-child td {border-bottom: none;}

/* box management */
.module-content-boxes .list {margin-bottom: 32px;}
.module-content-boxes td {white-space: nowrap;}
.module-content-boxes .box-title-cell {white-space: normal; width: 90%;}

/* article edit */
#article-edit-picture {width: 100%;}
#article-edit-picture-file {display: block; max-width: 200px; max-height: 200px; margin: 0 auto; border: 1px solid <?= $scheme_lighter ?>;}
#article-edit-picture-delete {padding: 6px; text-align: center;}
#article-edit-picture-upload {display: block; margin: 0 auto;}
#article-edit-time input[type=datetime-local] {margin-bottom: 10px;}

/* box manager */
#boxesedit {width: 100%;}
#boxesedit .cell {padding: 10px 20px 25px 10px;}
#boxesedit .cell > div {border: 1px solid <?= $scheme_smoke ?>; padding: 20px 15px;}

/* file manager */
#fman-action {border-bottom: 1px solid <?= $scheme_smoke ?>; margin-bottom: 10px;}
#fman-action h2 {margin-bottom: 6px;}
#fman-list {width: 100%; border-collapse: collapse; margin-bottom: 6px;}
#fman-list a {color: <?= $scheme_text ?>;}
#fman-list .fman-size {width: 15%;}
#fman-list .actions {width: 25%;}
#fman-list .fman-item a {display: block;}
#fman-list td {padding: 2px 4px; border: 1px solid <?= $scheme_white ?>; line-height: 200%;}
#fman-list tr:hover td {background-color: <?= $scheme_lightest ?>;}
#fman-list input {margin: 5px 4px 0 0; float: left;}
#fman-list .fman-uploaded td {background: <?= $scheme_lighter ?>;}
#fmanFiles {white-space: nowrap;}
.fman-menu {border-width: 1px 0 1px 0; border-style: solid; border-color: <?= $scheme_smoke ?>;}
.fman-menu, .fman-menu2 {margin-top: 5px; padding: 5px;}
.fman-menu a, .fman-menu span, .fman-menu2 a, .fman-menu2 span {border-right: 1px solid <?= $scheme_smoke ?>; padding-right: 8px; margin-right: 8px;}
.fman-spacer {height: 10px;}

/* galleries */
.gallery-savebutton {float: left; margin: 0 14px 0 0; display: block;}
#gallery-edit {float: left; margin: 14px 0; padding: 5px; border: 1px solid <?= $scheme_smoke_light ?>;}
.gallery-edit-image {float: left; margin: 5px;}
.gallery-edit-image input {cursor: default;}
.gallery-edit-image table {border: 1px solid <?= $scheme_smoke_dark ?>; padding: 10px; background-color: <?= $scheme_smoke ?>; cursor: move;}
.gallery-edit-image a {color: <?= $scheme_black ?>; cursor: pointer;}
.gallery-edit-image a.lightbox img {border: 1px solid <?= $scheme_smoke_text ?>; max-width: 300px;}

/* sqlex */
#sqlex {width: 100%; margin-top: 1em; border-collapse: collapse;}
#sqlex td {padding: 1em; border: 1px solid <?= $scheme_smoke ?>; vertical-align: top;}
#sqlex td:first-child ul {padding: 0; margin: 0; list-style-type: none;}
#sqlex li, #sqlex-result li {line-height: 160%;}
#sqlex-result {overflow: auto;}
#sqlex-result h2 {margin-bottom: 1em;}
#sqlex-result .list {width: 100%; background-color: <?= $scheme_white ?>; outline: 6px solid <?= $scheme_white ?>;}
#sqlex-result .list textarea {width: 100%;}

/* settings */
#settingsnav {width: 20%; float: left; margin-right: 1em;}
#settingsnav, #settingsnav a {font-size: 12px;}
#settingsnav.scrollfix-top {position: fixed; top: 10px; height: calc(100% - 100px); overflow: auto; z-index: 100;}
#settingsnav input[type=submit] {width: 100%;}
#settingsnav ul {padding: 0; margin: 0.5em 0 0 0; border: 1px solid <?= $scheme_smoke ?>; background-color: <?= $scheme_smoke_lighter ?>;}
#settingsnav li {display: block; list-style-type: none;}
#settingsnav li a {display: block; padding: 6px 11px; border-bottom: 1px solid <?= $scheme_smoke_light ?>; color: <?= $scheme_text ?>;}
#settingsnav a:hover, #settingsnav li.active a {background-color: <?= $scheme_smoke_dark ?>; color: <?= $scheme_black ?>;}

#settingsform {float: left; padding-bottom: 30em; width: 78%;}
#settingsform fieldset {margin: 0 0 5em 0;}
#settingsform label {font-weight: bold;}
#settingsform table {border-collapse: collapse;}
#settingsform table td {padding: 4px 8px; border-bottom: 1px solid <?= $scheme_smoke_med ?>;}
#settingsform table td:first-child {white-space: nowrap; border-right: 1px solid <?= $scheme_smoke_med ?>;}
#settingsform table th {padding-right: 8px; padding-left: 4px;}
#settingsform table tr:last-child td {border-bottom: none;}

/* plugins */
.plugin-list {width: 100%; table-layout: fixed;}
.plugin-list td {vertical-align: top;}
.plugin-list > thead > tr > th:first-child {width: 30%;}

/* busy overlay */
body.busy-overlay-active {overflow: hidden;}
#busy-overlay {position: fixed; left: 0; top: 0; z-index: 5000; width: 100%; height: 100%; cursor: wait; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;}
#busy-overlay > div {transition: opacity 1s; width: 100%; height: 100%; opacity: 0; background-color: rgba(0, 0, 0, 0.5);}
#busy-overlay.busy-overlay-visible > div {opacity: 1;}
#busy-overlay > div > div {position: absolute; left: 50%; top: 50%; margin: -60px 0 0 -150px; width: 300px; height: 120px; text-align: center;}
#busy-overlay > div > div > p {color: #fff; font-size: 1.8em;}

/* link styles */
a.normal {color: <?= $scheme_text ?>;}
a.invisible {color: <?= $scheme_smoke_text ?>;}
a.notpublic {font-style: italic; color: <?= $scheme_text ?>;}
a.invisible-notpublic {color: <?= $scheme_smoke_text ?>; font-style: italic;}
a.active-link {text-decoration: underline !important;}

/* highlight */
.hl {background-color: <?= $scheme_smoke_lighter ?>;}

/* messages */
.message {margin: 1em 0; padding: 13px 5px 13px 48px; background-color: <?= $scheme_smoke ?>; background-position: 5px 5px; background-repeat: no-repeat;}
.message ul {margin: 0; padding: 5px 0 0 15px;}
.message a {color: inherit; text-decoration: underline;}
.message a.button {color: unset; text-decoration: unset;}
.message-ok {background-color: <?= $scheme_bg_info ?>; background-image: url("../public/images/icons/info.png");}
.message-warn {background-color: <?= $scheme_bg_alert ?>; background-image: url("../public/images/icons/warning.png");}
.message-err {background-color: <?= $scheme_bg_danger ?>; background-image: url("../public/images/icons/error.png");}

/* preformatted */
pre {white-space: pre-wrap; overflow-wrap: anywhere; word-break: normal;}
pre.exception {max-height: 300px; margin: 1em 0; padding: 13px; overflow: auto; background-color: <?= $scheme_bg_danger ?>;}

/* form tables */
.formtable {border: 1px dotted <?= $scheme_smoke ?>; background-color: <?= $scheme_smoke_lightest ?>;}
.cform table {width: 100%; table-layout: fixed;}
.cform th {width: 111px;}
.cform th:first-child + td {width: 40%;}
.cform th:first-child + td:last-child {width: auto;}

/* text colors */
.text-success {color: #080;}
.text-warning {color: #FE7F00;}
.text-danger {color: #E71717;}

/* table cell colors */
.cell-success, .row-success > th, .row-success > td {background-color: <?= $dark ? '#001e00' : '#e1ffe1' ?>;}
.cell-warning, .row-warning > th, .row-warning > td {background-color: <?= $dark ? '#1e0f00' : '#fff0e1' ?>;}
.cell-danger, .row-danger > th, .row-danger > td {background-color: <?= $dark ? '#1b0303' : '#fde3e3' ?>;}
tr.even > td {background-color: <?= $scheme_smoke ?>;}
tr.od >d td {background-color: <?= $scheme_smoke_lightest ?>;}

/* form element sizes */
.arealine {width: 100%; height: 100px;}
.areasmall {width: 290px; height: 150px;}
.areasmall_100pwidth {width: 100%; height: 200px;}
.areasmallwide {width: 620px; height: 150px;}
.areamedium {width: 600px; height: 350px;}
.areabig {width: 100%; height: 400px;}
.areabigperex {width: 100%; height: 150px;}
.inputmini {width: 38px;}
.inputmini[type=number] {width:50px;}
.inputsmaller {width: 80px;}
.inputsmall {width: 145px;}
.inputmedium {width: 290px;}
.inputbig {width: 750px;}
.inputmax {width: 100%;}
.inputfat {padding: 8px 16px !important;}
.selectmedium {width: 294px;}
.selectbig {width: 753px;}
.selectmax {width: 100%;}

/* horizontal rule */
.hr {height: 10px; background-image: url("../public/images/hr<?= $dark_suffix ?>.gif"); background-position: left center; background-repeat: repeat-x;}
.hr hr {display: none;}

.paging {margin: 1em 0;}
.paging-label {display: none;}
.paging a {display: inline-block; padding: 0.3em 0.6em; border: 1px solid <?= $scheme_smoke ?>; text-decoration: none;}
.paging a.act, .paging a:hover {color: <?= $scheme_text ?>; background-color: <?= $scheme_smoke_light ?>;}
fieldset .paging a {background-color: <?= $scheme_white ?>;}
fieldset .paging a.act, fieldset .paging a:hover {background-color: <?= $scheme_smoke_dark ?>; color: <?= $scheme_white ?>;}

/* two-column layout */
.two-columns {width: 100%; table-layout: fixed; border: 1px solid <?= $scheme_smoke ?>; border-collapse: collapse;}
.two-columns > tbody > tr > td {width: 50%; padding: 5px 15px;}
.two-columns > tbody > tr > td:first-child {border-right: 1px solid <?= $scheme_smoke ?>;}
.two-columns > tbody > tr > td > h2 {margin: 20px 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid <?= $scheme_smoke ?>;}
.two-columns > tbody > tr > td > h2:first-child {margin-top: 5px;}
.two-columns > tbody > tr > td > form:last-child {margin-bottom: 0;}

/* list */
.list {margin: 10px 0; border-collapse: collapse; border: 1px solid <?= $scheme_smoke ?>; background-color: <?= $scheme_white ?>;}
.list.list-max {width: 100%;}
.list.list-half {min-width: 50%;}
.list > caption {padding: 15px; border-bottom: 3px double <?= $scheme_smoke_lightest ?>; background-color: <?= $scheme_smoke ?>; text-align: left;}
.list > * > tr > th, .list > thead > tr > td {font-weight: bold; background-color: <?= $scheme_smoke ?>;}
.list > * > tr > td, .list > * > tr > th {padding: 7px 15px; border-bottom: 1px solid <?= $scheme_smoke ?>;}
.list.list-hover > tbody > tr:hover:not(.nohover) > td {background-color: <?= $scheme_smoke_lightest_colored ?>;}
.list.list-noborder > * > tr > *, .list > * > tr.list-noborder > * {border: none;}
.list > tbody + tbody {border-top: 3px double <?= $scheme_smoke ?>;}
.list h3 {font-weight: normal; font-size: 16px;}
fieldset .list > thead > tr > td, fieldset .list > * th {background-color: <?= $scheme_smoke_dark ?>;}
fieldset .list > * tr > td {border-color: <?= $scheme_smoke_dark ?>;}


/* log */
.log-list {background-color: <?= $scheme_smoke_lightest ?>;}
.log-list a {color: unset;}
.log-list > tbody:hover > tr > td {background-color: <?= $scheme_smoke_lighter ?>;}
.log-list > tbody + tbody {border-top: 1px solid <?= $scheme_smoke_med ?>;}
.log-message {color: <?= $scheme_smoke_text_darker ?>;}
.log-message a {display: block;}
.log-message code {overflow-wrap: anywhere; word-break: normal;}

.log-search .log-time-presets {width: 50px;}

/* actions list */
.actions {white-space: nowrap;}
.actions a {margin-left: 0.5em;}
.actions a:first-child {margin-left: 0;}

/* radio group */
.radio-group {border: 1px solid <?= $scheme_smoke_dark ?>; background-color: <?= $scheme_white ?>; box-shadow: inset 0 0 4px <?= $scheme_alpha_shadow ?>;}
.radio-group label {display: block; float: left; padding: 5px; border-left: 1px solid <?= $scheme_smoke_dark ?>;}
.radio-group label:first-child {border-left: none;}

/* inline list */
.inline-list {padding-left: 0; padding-right: 0; list-style-type: none;}
.inline-list > li {display: inline-block; padding-left: 0.5em;}
.inline-list > li:after {content: "|"; padding-left: 0.5em; color: <?= $scheme_smoke_dark ?>;}
.inline-list > li:first-child {padding-left: 0;}
.inline-list > li:last-child:after {display: none;}
.inline-list > li > strong {padding-right: 0.2em;}

/* buttons */
a.button {display: inline-block; margin: 0; padding: 6px; border: 1px solid <?= $scheme_smoke_med ?>; background: <?= $scheme_smoke_lighter ?>; background: linear-gradient(to bottom, <?= $scheme_smoke_lightest ?>, <?= $scheme_smoke ?>); color: <?= $scheme_text ?>; vertical-align: middle; font-weight: normal; line-height: 1;}
a.button img.icon {margin: -1px 0 -1px 0; padding: 0 6px 0 0;}
a.button.block {display: block; margin: 6px;}
a.button.block img.icon {float: none; margin: 0; padding: 0 10px 0 0;}
a.button:hover {background: <?= $scheme_lightest ?>; background: linear-gradient(to bottom, <?= $scheme_lightest ?>, <?= $scheme_lighter ?>); border-color: <?= $scheme_lighter ?>;}
a.button.big, input[type=submit].button.big, input[type=button].button.big, input[type=reset].button.big, button.button.big {padding: 8px 12px; font-size: 13px;}
a.button.bigger, input[type=submit].button.bigger, input[type=button].button.bigger, input[type=reset].button.bigger, button.button.bigger {padding: 12px 18px; font-size: 14px;}
input[type=submit].button, input[type=button].button, input[type=reset].button, button.button {padding: 6px; font-size: 12px;}

/* inline separator */
.inline-separator {margin-left: 0.5em; padding-right: 0.5em; border-left: 1px solid <?= $scheme_smoke_med ?>;}

/* well */
.well {margin: 10px 0; padding: 10px; border: 1px solid <?= $scheme_smoke_med ?>; background-color: <?= $scheme_smoke_lighter ?>;}
.well h2 {font-weight: normal;}

/* sortables */
.sortable.ui-sortable {overflow: hidden;}
.sortable-handle {display: inline-block; vertical-align: middle; padding-right: 10px; width: 11px; height: 18px; background: url("../public/images/icons/drag-handle.png") left top no-repeat; cursor: move;}

/* generic */
.bborder {padding-bottom: 8px; margin-bottom: 12px; border-bottom: 1px solid <?= $scheme_smoke ?>;}
fieldset .bborder {border-color: <?= $scheme_smoke_text ?>;}
fieldset fieldset .bborder {border-color: <?= $scheme_smoke ?>;}
.backlink {display: block; font-weight: bold; padding-bottom: 10px;}
.icon {margin: -2px 0 0 0; padding-right: 4px; vertical-align: middle;}
.inline {display: inline;}
.hidden {display: none;}
.cleaner {clear: both;}
.micon {height: 15px; margin: 0 1px;}
.special {color: <?= $scheme_link ?>;}
.small {font-size: 10px;}
.block {display: block;}
.note {color: <?= $scheme_smoke_text ?>;}
.minwidth {min-width: 700px;}
.important {color: red;}
.highlight {color: <?= $scheme_link ?>;}
.max-area {width: 100%; height: 100%;}
.max-width {width: 100%;}
.half-width {width: 50%;}
.separated {margin-top: 1em;}
.clickable {cursor: pointer;}
.strike {text-decoration: line-through;}
.em {font-style: italic;}
.big-text {font-size: larger;}
.left {float: left; margin: 1px 10px 5px 1px;}
.right {float: right; margin: 1px 1px 5px 10px;}
.text-left {text-align: right;}
.text-center {text-align: center;}
.text-right {text-align: right;}
tr.valign-top > *, table.valign-top > * > tr > * {vertical-align: top;}
.cell-shrink {width: 0; white-space: nowrap;}
.error-border {border-color: #f00 !important;}
.no-bullets {list-style-type: none; padding-left: 10px;}
.ui-sortable-handle {-ms-touch-action:none; touch-action:none;}

/* tree */
.node-level-m0 {margin-left: 0 !important;}
.node-level-p0 {padding-left: 0 !important;}
.node-level-m1 {margin-left: 24px !important;}
.node-level-p1 {padding-left: 24px !important;}
.node-level-m2 {margin-left: 48px !important;}
.node-level-p2 {padding-left: 48px !important;}
.node-level-m3 {margin-left: 72px !important;}
.node-level-p3 {padding-left: 72px !important;}
.node-level-m4 {margin-left: 96px !important;}
.node-level-p4 {padding-left: 96px !important;}
.node-level-m5 {margin-left: 120px !important;}
.node-level-p5 {padding-left: 120px !important;}
.node-level-m6 {margin-left: 144px !important;}
.node-level-p6 {padding-left: 144px !important;}
.node-level-m7 {margin-left: 168px !important;}
.node-level-p7 {padding-left: 168px !important;}
.node-level-m8 {margin-left: 192px !important;}
.node-level-p8 {padding-left: 192px !important;}
.node-level-m9 {margin-left: 216px !important;}
.node-level-p9 {padding-left: 216px !important;}
.node-level-m10 {margin-left: 240px !important;}
.node-level-p10 {padding-left: 240px !important;}
.node-level-m11 {margin-left: 264px !important;}
.node-level-p11 {padding-left: 264px !important;}
.node-level-m12 {margin-left: 288px !important;}
.node-level-p12 {padding-left: 288px !important;}
.node-level-m13 {margin-left: 312px !important;}
.node-level-p13 {padding-left: 312px !important;}
.node-level-m14 {margin-left: 336px !important;}
.node-level-p14 {padding-left: 336px !important;}
.node-level-m15 {margin-left: 360px !important;}
.node-level-p15 {padding-left: 360px !important;}
.node-level-m16 {margin-left: 384px !important;}
.node-level-p16 {padding-left: 384px !important;}
.node-level-m17 {margin-left: 408px !important;}
.node-level-p17 {padding-left: 408px !important;}
.node-level-m18 {margin-left: 432px !important;}
.node-level-p18 {padding-left: 432px !important;}
.node-level-m19 {margin-left: 456px !important;}
.node-level-p19 {padding-left: 456px !important;}
.node-level-m20 {margin-left: 480px !important;}
.node-level-p20 {padding-left: 480px !important;}

<?= Extend::buffer('admin.style') ?>
