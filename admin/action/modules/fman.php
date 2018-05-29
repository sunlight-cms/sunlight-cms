<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Util\Filesystem;

defined('_root') or exit;

/* ----  priprava funkci  ---- */

$decodeFilename = function ($value, $encoded = true) {
    if ($encoded) {
        $value = @base64_decode($value);
    }

    return basename($value);
};

$encodeFilename = function ($value, $urlencode = true) {
    $output = base64_encode($value);
    if ($urlencode) {
        $output = rawurlencode($output);
    }

    return $output;
};

/* ----  priprava promennych  ---- */

$extensions = array(
    // archives
    'rar' => 'archive',
    'zip' => 'archive',
    'tar' => 'archive',
    'gz' => 'archive',
    'tgz' => 'archive',
    '7z' => 'archive',
    'cab' => 'archive',
    'xar' => 'archive',
    'xla' => 'archive',
    '777' => 'archive',
    'alz' => 'archive',
    'arc' => 'archive',
    'arj' => 'archive',
    'bz' => 'archive',
    'bz2' => 'archive',
    'bza' => 'archive',
    'bzip2' => 'archive',
    'dz' => 'archive',
    'gza' => 'archive',
    'gzip' => 'archive',
    'lzma' => 'archive',
    'lzs' => 'archive',
    'lzo' => 'archive',
    's7z' => 'archive',
    'taz' => 'archive',
    'tbz' => 'archive',
    'tz' => 'archive',
    'tzip' => 'archive',
    'dmg' => 'archive',

    // images
    'jpg' => 'image',
    'jpeg' => 'image',
    'png' => 'image',
    'gif' => 'image',
    'bmp' => 'image',
    'jp2' => 'image',
    'tga' => 'image',
    'pcx' => 'image',
    'tif' => 'image',
    'ppf' => 'image',
    'pct' => 'image',
    'pic' => 'image',
    'ai' => 'image',
    'ico' => 'image',

    // editable
    'sql' => 'editable',
    'php' => 'editable',
    'php3' => 'editable',
    'php4' => 'editable',
    'php5' => 'editable',
    'phtml' => 'editable',
    'py' => 'editable',
    'asp' => 'editable',
    'cgi' => 'editable',
    'shtml' => 'editable',
    'htaccess' => 'editable',
    'txt' => 'editable',
    'nfo' => 'editable',
    'rtf' => 'editable',
    'html' => 'editable',
    'htm' => 'editable',
    'xhtml' => 'editable',
    'css' => 'editable',
    'js' => 'editable',
    'ini' => 'editable',
    'bat' => 'editable',
    'inf' => 'editable',
    'me' => 'editable',
    'inc' => 'editable',
    'xml' => 'editable',
    'json' => 'editable',

    // media
    'wav' => 'media',
    'mp3' => 'media',
    'mid' => 'media',
    'rmi' => 'media',
    'wma' => 'media',
    'mpeg' => 'media',
    'mpg' => 'media',
    'wmv' => 'media',
    '3gp' => 'media',
    'mp4' => 'media',
    'm4a' => 'media',
    'xac' => 'media',
    'aif' => 'media',
    'au' => 'media',
    'avi' => 'media',
    'voc' => 'media',
    'snd' => 'media',
    'vox' => 'media',
    'ogg' => 'media',
    'flac' => 'media',
    'mov' => 'media',
    'aac' => 'media',
    'vob' => 'media',
    'amr' => 'media',
    'asf' => 'media',
    'rm' => 'media',
    'ra' => 'media',
    'ac3' => 'media',
    'swf' => 'media',
    'flv' => 'media',

    // executable
    'exe' => 'executable',
    'com' => 'executable',
    'dll' => 'executable',
);

Extend::call('admin.fman.extensions', array(
    'extensions' => &$extensions,
));

$continue = true;
$message = "";
$action_code = "";

$defdir = _userHomeDir();
$dir = _userNormalizeDir(_get('dir'));

// vytvoreni vychoziho adresare
if (!(file_exists($defdir) && is_dir($defdir))) {
    $test = mkdir($defdir, 0777, true);
    if (!$test) {
        $continue = false;
        $output .= _msg(_msg_err, _lang('admin.fman.msg.defdircreationfailure'));
    } else {
        chmod($defdir, 0777);
    }
}

$url_base = "index.php?p=fman&amp;";
$url = $url_base . "dir=" . rawurlencode($dir);
$uploaded = array();

/* ----  akce, vystup  ---- */

if ($continue) {

    /* ---  post akce  --- */
    if (isset($_POST['action'])) {

        switch (_post('action')) {

                // upload
            case "upload":
                $total = 0;
                $done = 0;
                foreach ($_FILES as $item) {
                    if (!is_array($item['name'])) continue;
                    for ($i = 0; isset($item['name'][$i]); ++$i) {
                        $name = _slugify($decodeFilename($item['name'][$i], false), false);
                        $tmp_name = $item['tmp_name'][$i];
                        $exists = file_exists($dir . $name);
                        if (is_uploaded_file($tmp_name) && _userCheckFilename($name) && (!$exists || isset($_POST['upload_rewrite']) && unlink($dir . $name))) {
                            if (_userMoveUploadedFile($tmp_name, $dir . $name)) {
                                ++$done;
                                $uploaded[$name] = true;
                            }
                        }
                        ++$total;
                    }
                }
                if ($done == $total) $micon = _msg_ok;
                else $micon = _msg_warn;
                $message = _msg($micon, _lang('admin.fman.msg.upload.done', array('*done*' => $done, '*total*' => $total)));
                break;

                // novy adresar
            case "newfolder":
                $name = $decodeFilename(_post('name'), false);
                if (!file_exists($dir . $name)) {
                    $test = mkdir($dir . $name);
                    if ($test) {
                        $message = _msg(_msg_ok, _lang('admin.fman.msg.newfolder.done'));
                        chmod($dir . $name, 0777);
                    } else {
                        $message = _msg(_msg_warn, _lang('admin.fman.msg.newfolder.failure'));
                    }
                } else {
                    $message = _msg(_msg_warn, _lang('admin.fman.msg.newfolder.failure2'));
                }
                break;

                // odstraneni
            case "delete":
                $name = $decodeFilename(_post('name'));
                if (file_exists($dir . $name)) {
                    if (!is_dir($dir . $name)) {
                        if (_userCheckFilename($name)) {
                            if (unlink($dir . $name)) {
                                $message = _msg(_msg_ok, _lang('admin.fman.msg.delete.done'));
                            } else {
                                $message = _msg(_msg_warn, _lang('admin.fman.msg.delete.failure'));
                            }
                        } else {
                            $message = _msg(_msg_warn, _lang('admin.fman.msg.disallowedextension'));
                        }
                    } else {
                        if (Filesystem::purgeDirectory($dir . $name, array(), $failedPath)) {
                            $message = _msg(_msg_ok, _lang('admin.fman.msg.delete.done'));
                        } else {
                            $message = _msg(_msg_warn, _lang('admin.fman.msg.delete.failure', array('*failed_path*' => _e($failedPath))));
                        }
                    }
                }
                break;

                // prejmenovani
            case "rename":
                $name = $decodeFilename(_post('name'));
                $newname = $decodeFilename(_post('newname'), false);
                if (file_exists($dir . $name)) {
                    if (!file_exists($dir . $newname)) {
                        if (_userCheckFilename($newname) && _userCheckFilename($name)) {
                            if (rename($dir . $name, $dir . $newname)) {
                                $message = _msg(_msg_ok, _lang('admin.fman.msg.rename.done'));
                            } else {
                                $message = _msg(_msg_warn, _lang('admin.fman.msg.rename.failure'));
                            }
                        } else {
                            $message = _msg(_msg_warn, _lang('admin.fman.msg.disallowedextension'));
                        }
                    } else {
                        $message = _msg(_msg_warn, _lang('admin.fman.msg.exists'));
                    }
                }
                break;

                // uprava
            case "edit":
                $name = $decodeFilename(_post('name'), false);
                $content = _post('content');
                if (_userCheckFilename($name)) {
                    $file = fopen($dir . $name, "w");
                    if ($file) {
                        fwrite($file, $content);
                        fclose($file);
                        $message = _msg(_msg_ok, _lang('admin.fman.msg.edit.done') . " <small>(" . _formatTime(time()) . ")</small>");
                    } else {
                        $message = _msg(_msg_warn, _lang('admin.fman.msg.edit.failure'));
                    }
                } else {
                    $message = _msg(_msg_warn, _lang('admin.fman.msg.disallowedextension'));
                }
                break;

                // presun
            case "move":
                $newdir = _arrayRemoveValue(explode("/", _post('param')), "");
                $newdir = implode("/", $newdir);
                if (substr($newdir, -1, 1) != "/") {
                    $newdir .= "/";
                }
                $newdir = Filesystem::parsePath($dir . $newdir);
                if (_userCheckPath($newdir, false)) {
                    $done = 0;
                    $total = 0;

                    foreach ($_POST as $var => $val) {
                        if ($var == "action" || $var == "param") {
                            continue;
                        }
                        $val = $decodeFilename($val);
                        if (file_exists($dir . $val) && !file_exists($newdir . $val) && !is_dir($dir . $val) && _userCheckFilename($val)) {
                            if (rename($dir . $val, $newdir . $val)) {
                                $done++;
                            }
                        }

                        $total++;
                    }

                    if ($done == $total) {
                        $micon = _msg_ok;
                    } else {
                        $micon = _msg_warn;
                    }
                    $message = _msg($micon, _lang('admin.fman.msg.move.done', array('*done*' => $done, '*total*' => $total)));
                } else {
                    $message = _msg(_msg_warn, _lang('admin.fman.msg.rootlimit'));
                }
                break;

                // odstraneni vyberu
            case "deleteselected":
                $done = 0;
                $total = 0;

                foreach ($_POST as $var => $val) {
                    if ($var == "action" || $var == "param") {
                        continue;
                    }
                    $val = $decodeFilename($val);
                    if (file_exists($dir . $val) && !is_dir($dir . $val) && _userCheckFilename($val)) {
                        if (unlink($dir . $val)) {
                            $done++;
                        }
                    }

                    $total++;
                }

                if ($done == $total) {
                    $micon = _msg_ok;
                } else {
                    $micon = _msg_warn;
                }
                $message = _msg($micon, _lang('admin.fman.msg.deleteselected.done', array('*done*' => $done, '*total*' => $total)));
                break;

                // pridani vyberu do galerie - formular pro vyber galerie
            case "addtogallery_showform":
                if (_priv_admingallery && _priv_admincontent) {
                    $_GET['a'] = "addtogallery";
                }
                break;

                // pridani vyberu do galerie - ulozeni
            case "addtogallery":
                if (_priv_admingallery && _priv_admincontent) {

                    // priprava promennych
                    $counter = 0;
                    $galid = (int) _post('gallery');

                    // vlozeni obrazku
                    if (DB::count(_root_table, 'id=' . DB::val($galid) . ' AND type=' . _page_gallery) !== 0) {

                        // nacteni nejmensiho poradoveho cisla
                        $smallestord = DB::queryRow("SELECT ord FROM " . _images_table . " WHERE home=" . $galid . " ORDER BY ord LIMIT 1");
                        if ($smallestord !== false) {
                            $smallestord = $smallestord['ord'];
                        } else {
                            $smallestord = 1;
                        }

                        // posunuti poradovych cisel
                        DB::update(_images_table, 'home=' . $galid, array('ord' => DB::raw('ord+' . (count($_POST) - 2))));

                        // cyklus
                        $sql = "";
                        foreach ($_POST as $var => $val) {
                            if ($var == "action" || $var == "param") {
                                continue;
                            }
                            $val = $decodeFilename($val);
                            $ext = pathinfo($val);
                            if (isset($ext['extension'])) {
                                $ext = strtolower($ext['extension']);
                            } else {
                                $ext = "";
                            }
                            if (file_exists($dir . $val) && !is_dir($dir . $val) && in_array($ext, Core::$imageExt)) {
                                $sql .= "(" . $galid . "," . ($smallestord + $counter) . ",'','','" . substr($dir . $val, 3) . "'),";
                                ++$counter;
                            }
                        }

                        // vlozeni
                        if ($counter != 0) {
                            $sql = trim($sql, ",");
                            DB::query("INSERT INTO " . _images_table . " (home,ord,title,prev,full) VALUES " . $sql);
                        }

                        // zprava
                        $message = _msg(_msg_ok, _lang('admin.fman.addtogallery.done', array("*done*" => $counter)));

                    } else {
                        $message = _msg(_msg_warn, _lang('global.badinput'));
                    }

                }
                break;

        }

    }

    /* ---  get akce  --- */
    if (isset($_GET['a'])) {

        $action_acbonus = "";
        $action_form_class = null;

        // vyber akce
        switch (_get('a')) {

                // novy adresar
            case "newfolder":
                $action_submit = "global.create";
                $action_title = "admin.fman.menu.createfolder";
                $action_code = "
      <tr>
      <th>" . _lang('global.name') . ":</th>
      <td><input type='text' name='name' class='inputmedium' maxlength='64'></td>
      </tr>
      ";
                break;

                // odstraneni
            case "delete":
                if (isset($_GET['name'])) {
                    $name = _get('name');
                    $action_submit = "global.do";
                    $action_title = "admin.fman.delete.title";
                    $action_code = "
        <tr>
        <td colspan='2'>" . _lang('admin.fman.delask', array("*name*" => _e($decodeFilename($name)))) . "<input type='hidden' name='name' value='" . _e($name) . "'></td>
        </tr>
        ";
                }
                break;

                // prejmenovani
            case "rename":
                if (isset($_GET['name'])) {
                    $name = _get('name');
                    $action_submit = "global.do";
                    $action_title = "admin.fman.rename.title";
                    $action_code = "
        <tr>
        <th>" . _lang('admin.fman.newname') . ":</th>
        <td><input type='text' name='newname' class='inputmedium' maxlength='64' value='" . _e($decodeFilename($name)) . "'><input type='hidden' name='name' value='" . _e($name) . "'></td>
        </tr>
        ";
                }
                break;

                // uprava
            case "edit":

                // priprava
                $continue = false;
                if (isset($_GET['name'])) {
                    $name = _get('name');
                    $dname = $decodeFilename($name);
                    $ext = strtolower(pathinfo($dname, PATHINFO_EXTENSION));
                    if (file_exists($dir . $dname)) {
                        if (_userCheckFilename($dname)) {
                            $new = false;
                            $continue = true;
                            $content = file_get_contents($dir . $dname);
                        } else {
                            $message = _msg(_msg_warn, _lang('admin.fman.msg.disallowedextension'));
                        }
                    }
                } else {
                    $name = "bmV3LnR4dA==";
                    $ext = 'txt';
                    $content = "";
                    $new = true;
                    $continue = true;
                }

                // formular
                if ($continue) {
                    $action_submit = "global.save";
                    $action_acbonus = (!$new ? "&amp;a=edit&amp;name=" . $name : '');
                    $action_title = "admin.fman.edit.title";

                    $action_form_class = 'cform';

                    $action_code = "
        <tr>
        <th>" . _lang('global.name') . "</th>
        <td><input type='text' name='name' class='inputmedium' maxlength='64' value='" . _e($decodeFilename($name)) . "'> <small>" . _lang('admin.fman.edit.namenote' . ($new ? '2' : '')) . "</small></td>
        </tr>

        <tr class='valign-top'>
        <th>" . _lang('admin.content.form.content') . "</th>
        <td><textarea rows='25' cols='94' class='areabig editor' data-editor-mode='code' data-editor-format='" . $ext . "' name='content' wrap='off'>" . _e($content) . "</textarea></td>
        </tr>
        ";
                }

                break;

                // upload
            case "upload":
                $action_submit = "global.send";
                $action_title = "admin.fman.menu.upload";
                $action_code = "
      <tr class='valign-top'>
      <th>" . _lang('admin.fman.file') . ":</th>
      <td id='fmanFiles'><input type='file' name='uf0[]' multiple> <a href='#' onclick='return Sunlight.admin.fmanAddFile();'>" . _lang('admin.fman.upload.addfile') . "</a></td>
      </tr>

      <tr>
      <td></td>
      <td>
          <label><input type='checkbox' name='upload_rewrite' value='1'> " . _lang('global.uploadrewrite') . "</label>
          " . _renderUploadLimit() . "
      </td>
      </tr>
      ";
                break;

                // addtogallery
            case "addtogallery":
                $action_submit = "global.insert";
                $action_acbonus = "";
                $action_title = "admin.fman.menu.addtogallery";

                // load and check images
                $images_load = array();
                foreach ($_POST as $var => $val) {
                    if ($var == "action" || $var == "param") {
                        continue;
                    }

                    $images_load[] = $val;
                }

                $images = "";
                $counter = 0;
                foreach ($images_load as $images_load_image) {
                    $images_load_image = pathinfo(base64_decode($images_load_image));
                    if (isset($images_load_image['extension']) && in_array(strtolower($images_load_image['extension']), Core::$imageExt)) {
                        $images .= "<input type='hidden' name='f" . $counter . "' value='" . base64_encode($images_load_image['basename']) . "'>\n";
                        ++$counter;
                    }
                }

                if ($counter != 0) {
                    $action_code = "
      <tr>
      <th>" . _lang('admin.fman.addtogallery.galllery') . "</th>
      <td>
      " . \Sunlight\Admin\Admin::rootSelect("gallery", array('type' => _page_gallery)) . "
      " . $images . "
      </td>
      </tr>

      <tr>
      <th>" . _lang('admin.fman.addtogallery.counter') . "</th>
      <td>" . $counter . "</td>
      </tr>
      ";
                } else {
                    $message = _msg(_msg_warn, _lang('admin.fman.addtogallery.noimages'));
                }
                break;

        }

        // dokonceni kodu
        if ($action_code != "") {

            $action_code = "
<div id='fman-action'>
<h2>" . _lang($action_title) . "</h2>
<form action='" . $url . $action_acbonus . "'" . (($action_form_class !== null) ? " class='" . $action_form_class . "'" : '') . " method='post' enctype='multipart/form-data'>
<input type='hidden' name='action' value='" . _e(_get('a')) . "'>
<table class='formtable'>
" . $action_code . "

  <tr>
  <td></td>
  <td><input type='submit' value='" . _lang($action_submit) . "' accesskey='s'> <a href='" . $url . "'>" . _lang('global.cancel') . "</a></td>
  </tr>

</table>
" . _xsrfProtect() . "</form>
</div>
";

        }

    }

    /* ---  vystup  --- */

    // menu, formular akce
    $output .= $message . "
    <a id='top'></a>
    <p class='fman-menu'>
    <a href='" . $url . "&amp;a=upload'>" . _lang('admin.fman.menu.upload') . "</a>
    <a href='" . $url . "&amp;a=edit'>" . _lang('admin.fman.menu.createfile') . "</a>
    <a href='" . $url . "&amp;a=newfolder'>" . _lang('admin.fman.menu.createfolder') . "</a>
    " . ((_priv_admingallery && _priv_admincontent) ? "<a href='#' onclick='return Sunlight.admin.fmanAddSelectedToGallery()'>" . _lang('admin.fman.menu.addtogallery') . "</a>" : '') . "
    <a href='" . $url_base . "dir=" . rawurlencode($defdir) . "'>" . _lang('admin.fman.menu.home') . "</a>
    <strong>" . _lang('admin.fman.currentdir') . ":</strong> " . substr($dir, strlen(_root)) . "
    </p>

    " . $action_code;

    // vypis
    $output .= "
    <form action='" . $url . "' method='post' name='filelist'>
    <input type='hidden' name='action' value='-1'>
    <input type='hidden' name='param' value='-1'>
    <table id='fman-list'>
    <tr><td width='60%'></td><td width='15%'></td><td width='25%'></td></tr>
    ";

    $highlight = false;

    // adresare
    $handle = opendir($dir);
    $items = array();
    while (($item = readdir($handle)) !== false) {
        if (is_dir($dir . $item) && $item != "." && $item != "..") {
            $items[] = $item;
        }
    }
    natsort($items);
    $items = array_merge(array(".."), $items);
    $dircounter = 0;
    foreach ($items as $item) {

        // adresar nebo odkaz na nadrazeny adresar
        if ($item == "..") {
            if (($dirhref = _userCheckPath($dir . $item, false, true)) === false) {
                continue;
            }
        } else {
            $dirhref = $dir . $item;
        }

        if ($highlight) {
            $hl_class = " class='hl'";
        } else {
            $hl_class = "";
        }

        $output .= "
        <tr" . $hl_class . ">
        <td colspan='" . (($item == "..") ? "3" : "2") . "'><a href='" . $url_base . "dir=" . rawurlencode($dirhref) . "/'><img src='images/icons/fman/dir.png' alt='dir' class='icon'>" . _e(_cutText($item, 64, false)) . "</a></td>
        " . (($item != "..") ? "<td class='actions'>
            <a class='button' href='" . $url . "&amp;a=delete&amp;name=" . $encodeFilename($item) . "'><img src='images/icons/delete.png' alt='del' class='icon'>" . _lang('global.delete') . "</a>
            <a class='button' href='" . $url . "&amp;a=rename&amp;name=" . $encodeFilename($item) . "'><img src='images/icons/rename.png' alt='rename' class='icon'>" . _lang('admin.fman.rename') . "</a>
        </td>" : '') . "
        </tr>\n";

        $highlight = !$highlight;
        ++$dircounter;
    }

    if ($dircounter !== 0) {
        $output .= "<tr><td colspan='3'> </td></tr>";
    }

    // soubory
    rewinddir($handle);
    $items = array();
    while (($item = readdir($handle)) !== false) {
        if (!is_dir($dir . $item) && $item != "..") {
            $items[] = $item;
        }
    }
    natsort($items);
    $filecounter = 0;
    $sizecounter = 0;
    foreach ($items as $item) {
        ++$filecounter;
        $row_classes = array();

        // ikona
        $iteminfo = pathinfo($item);
        if (!isset($iteminfo['extension'])) {
            $iteminfo['extension'] = "";
        }
        $ext = strtolower($iteminfo['extension']);
        $image = false;

        $icon = isset($extensions[$ext]) ? $extensions[$ext] : 'other';
        $image = $icon === 'image';

        $filesize = filesize($dir . $item);

        if ($highlight) {
            $row_classes[] = 'hl';
        }
        if (isset($uploaded[$item])) {
            $row_classes[] = 'fman-uploaded';
        }

        $output .= "
        <tr class='" . implode(' ', $row_classes) . "'>
        <td><input type='checkbox' name='f" . $filecounter . "' id='f" . $filecounter . "' value='" . $encodeFilename($item, false) . "'> <a href='" . _e($dir . $item) . "' target='_blank'" . ($image ? ' class="lightbox" data-gallery-group="fman"' : '') . "><img src='images/icons/fman/" . $icon . ".png' alt='file' class='icon'>" . _e(_cutText($item, 64, false)) . "</a></td>
        <td>" . _formatFilesize($filesize) . "</td>
        <td class='actions'>". (_userCheckFilename($item) ?
            "<a class='button' href='" . $url . "&amp;a=delete&amp;name=" . $encodeFilename($item) . "'><img src='images/icons/delete.png' alt='del' class='icon'>" . _lang('global.delete') . "</a>  "
            . "<a class='button' href='" . $url . "&amp;a=rename&amp;name=" . $encodeFilename($item) . "'><img src='images/icons/rename.png' alt='rename' class='icon'>" . _lang('admin.fman.rename') . "</a>  "
            . (($icon == "editable") ? "<a class='button' href='" . $url . "&amp;a=edit&amp;name=" . $encodeFilename($item) . "'><img src='images/icons/edit.png' alt='edit' class='icon'>" . _lang('admin.fman.edit') . "</a>" : '')
        : '') . "</td>
        </tr>\n";

        $sizecounter += $filesize;

        $highlight = !$highlight;
    }

    if ($filecounter === 0 && $dircounter === 0) {
        $output .= "<tr><td colspan='3'>" . _lang('global.nokit') . "</td></tr>\n";
    }

    $output .= "
    </table>
    " . _xsrfProtect() . "</form>

    <p class='fman-menu'>
    <span><strong>" . _lang('admin.fman.filecounter') . ":</strong> " . $filecounter . " <small>(" . _formatFilesize($sizecounter) . ")</small></span>
    <a href='#' onclick='return Sunlight.admin.fmanSelect(" . $filecounter . ", 1)'>" . _lang('admin.fman.selectall') . "</a>
    <a href='#' onclick='return Sunlight.admin.fmanSelect(" . $filecounter . ", 2)'>" . _lang('admin.fman.deselectall') . "</a>
    <a href='#' onclick='return Sunlight.admin.fmanSelect(" . $filecounter . ", 3)'>" . _lang('admin.fman.inverse') . "</a>
    <strong>" . _lang('admin.fman.selected') . ":</strong>
    <a href='#' onclick='return Sunlight.admin.fmanMoveSelected()'>" . _lang('admin.fman.selected.move') . "</a>
    <a href='#' onclick='return Sunlight.admin.fmanDeleteSelected()'>" . _lang('admin.fman.selected.delete') . "</a>
    <a href='#top'><span class='big-text'>&uarr;</span></a>
    </p>
    ";

}
