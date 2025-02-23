<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Image\ImageService;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Util\StringHelper;

defined('SL_ROOT') or exit;

$extensions = [
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
    'webp' => 'image',
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
];

Extend::call('admin.fman.extensions', [
    'extensions' => &$extensions,
]);

$continue = true;
$message = '';
$action_code = '';

$defdir = User::getHomeDir();
$dir = Request::get('dir');

if ($dir !== null) {
    $dir = User::normalizeDir(SL_ROOT . $dir);
} else {
    $dir = $defdir;
}

$uploaded = [];

// create default dir
Filesystem::ensureDirectoryExists($defdir, true);

// functions
$fmanUrl = function (array $query = []) use ($dir) {
    $urlDir = substr($query['dir'] ?? $dir, strlen(SL_ROOT));

    return Router::admin('fman', ['query' => ['dir' => $urlDir] + $query]);
};

$decodeFilename = function ($value, $encoded = true) {
    if ($encoded) {
        $value = @base64_decode($value);
    }

    return basename($value);
};

$encodeFilename = function ($value) {
    return base64_encode($value);
};

$getSelectedFiles = function () use ($decodeFilename) {
    return array_map($decodeFilename,  Arr::filterKeys($_POST, 'file_'));
};

// actions and output
if ($continue) {
    // post actions
    if (isset($_POST['action'])) {
        switch (Request::post('action')) {
            // upload
            case 'upload':
                $total = 0;
                $done = 0;

                foreach ($_FILES as $item) {
                    if (!is_array($item['name'])) continue;

                    for ($i = 0; isset($item['name'][$i]); ++$i) {
                        $name = StringHelper::slugify($decodeFilename($item['name'][$i], false), ['lower' => false]);
                        $tmp_name = $item['tmp_name'][$i];
                        $exists = file_exists($dir . $name);

                        if (
                            is_uploaded_file($tmp_name)
                            && User::checkFilename($name)
                            && (!$exists || isset($_POST['upload_rewrite']) && @unlink($dir . $name))
                            && User::moveUploadedFile($tmp_name, $dir . $name)
                        ) {
                            ++$done;
                            $uploaded[$name] = true;
                        }

                        ++$total;
                    }
                }

                $message = Message::render($done == $total ? Message::OK : Message::WARNING, _lang('admin.fman.msg.upload.done', ['%done%' => _num($done), '%total%' => _num($total)]));
                break;

            // new dir
            case 'newfolder':
                $name = $decodeFilename(Request::post('name'), false);

                if (!file_exists($dir . $name)) {
                    $test = @mkdir($dir . $name);

                    if ($test) {
                        $message = Message::ok(_lang('admin.fman.msg.newfolder.done'));
                        chmod($dir . $name, 0777);
                    } else {
                        $message = Message::warning(_lang('admin.fman.msg.newfolder.failure'));
                    }
                } else {
                    $message = Message::warning(_lang('admin.fman.msg.newfolder.failure2'));
                }
                break;

            // delete
            case 'delete':
                $name = $decodeFilename(Request::post('name'));

                if (file_exists($dir . $name)) {
                    if (!is_dir($dir . $name)) {
                        if (User::checkFilename($name)) {
                            if (@unlink($dir . $name)) {
                                $message = Message::ok(_lang('admin.fman.msg.delete.done'));
                            } else {
                                $message = Message::warning(_lang('admin.fman.msg.delete.failure'), true);
                            }
                        } else {
                            $message = Message::warning(_lang('admin.fman.msg.disallowedextension'));
                        }
                    } elseif (Filesystem::removeDirectory($dir . $name, $failedPath)) {
                        $message = Message::ok(_lang('admin.fman.msg.delete.done'));
                    } else {
                        $message = Message::warning(_lang('admin.fman.msg.delete.failure', ['%failed_path%' => _e($failedPath)]), true);
                    }
                }
                break;

            // rename
            case 'rename':
                $name = $decodeFilename(Request::post('name'));

                if (!file_exists($dir . $name)) {
                    break;
                }

                $is_file = is_file($dir . $name);
                $newname = Request::post('newname');

                do {
                    if ($is_file && !User::checkFilename($name)) {
                        $message = Message::warning(_lang('admin.fman.msg.disallowedextension'));
                        break;
                    }

                    if (!file_exists($dir . $name)) {
                        $message = Message::warning(_lang('global.badinput'));
                        break;
                    }

                    $newname_normalized = User::checkPath($dir . $newname, $is_file, true);

                    if ($newname_normalized === false) {
                        $message = Message::warning(_lang($is_file && !User::checkFilename($newname) ? 'admin.fman.msg.disallowedextension' : 'admin.fman.msg.rootlimit'));
                        break;
                    }

                    if (file_exists($newname_normalized)) {
                        $message = Message::warning(_lang('admin.fman.msg.exists'));
                        break;
                    }

                    try {
                        Filesystem::ensureDirectoryExists(dirname($newname_normalized), true);
                    } catch(RuntimeException $e) {
                        $message = Message::warning(_lang('admin.fman.msg.newfolder.failure'));
                        break;
                    }

                    if (!@rename($dir . $name, $newname_normalized)) {
                        $message = Message::warning(_lang('admin.fman.msg.rename.failure'));
                        break;
                    }

                    $message = Message::ok(_lang('admin.fman.msg.rename.done'));
                } while(false);
                break;

            // edit
            case 'edit':
                $name = $decodeFilename(Request::post('name'), false);
                $content = Request::post('content');

                if (User::checkFilename($name)) {
                    $file = @fopen($dir . $name, 'w');

                    if ($file) {
                        fwrite($file, $content);
                        fclose($file);
                        $message = Message::ok(_lang('admin.fman.msg.edit.done') . ' <small>(' . GenericTemplates::renderTime(time(), 'saved_msg') . ')</small>', true);
                    } else {
                        $message = Message::warning(_lang('admin.fman.msg.edit.failure'));
                    }
                } else {
                    $message = Message::warning(_lang('admin.fman.msg.disallowedextension'));
                }
                break;

            // move
            case 'move':
                $newdir = Request::post('param');

                if (substr($newdir, -1, 1) != '/') {
                    $newdir .= '/';
                }

                $newdir = Filesystem::parsePath($dir . $newdir, false, true);

                if (User::checkPath($newdir, false)) {
                    $done = 0;
                    $total = 0;

                    try {
                        Filesystem::ensureDirectoryExists($newdir, true);
                    } catch (RuntimeException $e) {
                        $message = Message::warning(_lang('admin.fman.msg.newfolder.failure'));
                        break;
                    }

                    foreach ($getSelectedFiles() as $file) {
                        if (is_file($dir . $file) && !is_file($newdir . $file) && User::checkFilename($file) && @rename($dir . $file, $newdir . $file)) {
                            $done++;
                        }

                        $total++;
                    }

                    $message = Message::render($done == $total ? Message::OK : Message::WARNING, _lang('admin.fman.msg.move.done', ['%done%' => _num($done), '%total%' => _num($total)]));
                } else {
                    $message = Message::warning(_lang('admin.fman.msg.rootlimit'));
                }
                break;

            // download selected
            case 'downloadselected':
                // locate selected files
                $selected = [];

                foreach ($getSelectedFiles() as $file) {
                    if (is_file($dir . $file) && User::checkFilename($file)) {
                        $selected[] = $file;
                    }
                }

                if (count($selected) > 0) {
                    $tmpFile = Filesystem::createTmpFile();
                    $zip = new ZipArchive();
                    $zip->open($tmpFile->getPathname(), ZipArchive::OVERWRITE);

                    foreach ($selected as $sel) {
                        $zip->addFile($dir . $sel, $sel);
                    }

                    $zip->close();

                    Response::downloadFile($tmpFile->getPathname(), sprintf('%s.zip', basename($dir)));
                }
                break;

            // remove selected
            case 'deleteselected':
                $done = 0;
                $total = 0;

                foreach ($getSelectedFiles() as $file) {
                    if (is_file($dir . $file) && User::checkFilename($file) && @unlink($dir . $file)) {
                        $done++;
                    }

                    $total++;
                }

                $message = Message::render($done == $total ? Message::OK : Message::WARNING, _lang('admin.fman.msg.deleteselected.done', ['%done%' => _num($done), '%total%' => _num($total)]));
                break;

            // add selection to gallery - choose gallery
            case 'addtogallery_showform':
                if (User::hasPrivilege('admingallery') && User::hasPrivilege('admincontent')) {
                    $_GET['a'] = 'addtogallery';
                }
                break;

            // add selection to gallery - perform
            case 'addtogallery':
                if (User::hasPrivilege('admingallery') && User::hasPrivilege('admincontent')) {
                    $counter = 0;
                    $galid = (int) Request::post('gallery');
                    $moveords = (bool) Request::post('moveords');

                    // insert images
                    if (DB::count('page', 'id=' . DB::val($galid) . ' AND type=' . Page::GALLERY . ' AND level<=' . User::getLevel()) !== 0) {
                        // get the initial order number
                        $ord = DB::queryRow(
                            'SELECT COALESCE(' . ($moveords ? 'MIN(ord)' : 'MAX(ord) + 1') . ', 1) AS ord'
                            . ' FROM ' . DB::table('gallery_image')
                            . ' WHERE home=' . $galid
                        )['ord'];

                        // prepare rows
                        $rows = [];

                        foreach ($getSelectedFiles() as $file) {
                            if (is_file($dir . $file) && ImageService::isImage($file)) {
                                $rows[] = [
                                    'home' => $galid,
                                    'ord' => $ord++,
                                    'title' => '',
                                    'prev' => '',
                                    'full' => substr($dir . $file, strlen(SL_ROOT)),
                                ];
                            }
                        }

                        // move order numbers
                        if ($moveords) {
                            DB::update('gallery_image', 'home=' . $galid, ['ord' => DB::raw('ord+' . count($rows))], null);
                        }

                        // insert
                        DB::insertMulti('gallery_image', $rows);

                        $message = Message::ok(_lang('admin.fman.addtogallery.done', ['%done%' => _num(count($rows))]));
                    } else {
                        $message = Message::warning(_lang('global.badinput'));
                    }
                }
                break;
        }
    }

    // get actions
    if (isset($_GET['a'])) {
        $action_query = [];
        $action_form_class = null;

        switch (Request::get('a')) {
            // new folder
            case 'newfolder':
                $action_submit = 'global.create';
                $action_title = 'admin.fman.menu.createfolder';
                $action_code = '
      <tr>
      <th>' . _lang('global.name') . ':</th>
      <td>' . Form::input('text', 'name', null, ['class' => 'inputmedium', 'maxlength' => 64]) . '</td>
      </tr>
      ';
                break;

            // delete
            case 'delete':
                if (isset($_GET['name'])) {
                    $name = Request::get('name');
                    $action_submit = 'global.do';
                    $action_title = 'admin.fman.delete.title';
                    $action_code = '
        <tr>
        <td colspan="2">' . _lang('admin.fman.delask', ['%name%' => _e($decodeFilename($name))]) . Form::input('hidden', 'name', $name) . '</td>
        </tr>
        ';
                }
                break;

            // rename
            case 'rename':
                if (isset($_GET['name'])) {
                    $name = Request::get('name');
                    $action_submit = 'global.do';
                    $action_title = 'admin.fman.rename.title';
                    $action_code = '
        <tr>
        <th>' . _lang('admin.fman.newname') . ':</th>
        <td>' . Form::input('text', 'newname', $decodeFilename($name), ['class' => 'inputmedium', 'maxlength' => 64]) . Form::input('hidden', 'name', $name) . '</td>
        </tr>
        ';
                }
                break;

            // edit
            case 'edit':
                $continue = false;

                if (isset($_GET['name'])) {
                    $name = Request::get('name');
                    $dname = $decodeFilename($name);
                    $ext = strtolower(pathinfo($dname, PATHINFO_EXTENSION));

                    if (file_exists($dir . $dname)) {
                        if (User::checkFilename($dname)) {
                            $new = false;
                            $continue = true;
                            $content = file_get_contents($dir . $dname);
                        } else {
                            $message = Message::warning(_lang('admin.fman.msg.disallowedextension'));
                        }
                    }
                } else {
                    $name = 'bmV3LnR4dA==';
                    $ext = 'txt';
                    $content = '';
                    $new = true;
                    $continue = true;
                }

                // form
                if ($continue) {
                    $action_submit = 'global.save';
                    $action_title = 'admin.fman.edit.title';

                    if (!$new) {
                        $action_query += ['a' => 'edit', 'name' => $name];
                    }

                    $action_form_class = 'cform';

                    $action_code = '
        <tr>
        <th>' . _lang('global.name') . '</th>
        <td>' . Form::input('text', 'name', $decodeFilename($name), ['class' => 'inputmedium', 'maxlength' => 64]) . ' <small>' . _lang('admin.fman.edit.namenote' . ($new ? '2' : '')) . '</small></td>
        </tr>

        <tr class="valign-top">
        <th>' . _lang('admin.content.form.content') . '</th>
        <td>' . Admin::editor('fman-edit', 'content', $content, ['mode' => 'code', 'format' => $ext, 'wrap' => 'off']) . '</td>
        </tr>
        ';
                }

                break;

            // upload
            case 'upload':
                $action_submit = 'global.send';
                $action_title = 'admin.fman.menu.upload';
                $action_code = '
      <tr class="valign-top">
      <th>' . _lang('admin.fman.file') . ':</th>
      <td id="fmanFiles">' . Form::input('file', 'uf0[]', null, ['multiple' => 'true']) . ' <a href="#" onclick="return Sunlight.admin.fmanAddFile();">' . _lang('admin.fman.upload.addfile') . '</a></td>
      </tr>

      <tr>
      <td></td>
      <td>
          <label>' . Form::input('checkbox', 'upload_rewrite', '1') . ' ' . _lang('global.uploadrewrite') . '</label>
          ' . Environment::renderUploadLimit() . '
      </td>
      </tr>
      ';
                break;

            // add to gallery
            case 'addtogallery':
                $action_submit = 'global.insert';
                $action_title = 'admin.fman.menu.addtogallery';

                // load and check images
                $images = '';
                $counter = 0;

                foreach ($getSelectedFiles() as $file) {
                    if (ImageService::isImage($file)) {
                        $images .= Form::input('hidden', 'file_' . $counter, $encodeFilename($file)) . "\n";
                        ++$counter;
                    }
                }

                if ($counter != 0) {
                    $action_code = '
      <tr>
      <th>' . _lang('admin.fman.addtogallery.galllery') . '</th>
      <td>
      ' . Admin::pageSelect('gallery', ['type' => Page::GALLERY]) . '
      ' . $images . '
      </td>
      </tr>

      <tr>
      <th>' . _lang('admin.fman.addtogallery.counter') . '</th>
      <td>' . _num($counter) . '</td>
      </tr>

      <tr>
      <th>' . _lang('admin.content.form.ord') . '</th>
      <td><label>' . Form::input('checkbox', 'moveords', '1', ['checked' => true]) . ' ' . _lang('admin.content.manageimgs.moveords') . '</label></td>
      </tr>
      ';
                } else {
                    $message = Message::warning(_lang('admin.fman.addtogallery.noimages'));
                }
                break;
        }

        // finish action code
        if ($action_code != '') {
            $action_code = '
<div id="fman-action">
<h2>' . _lang($action_title) . '</h2>
' . Form::start('fman-action', ['action' => $fmanUrl($action_query), 'class' => $action_form_class, 'enctype' => 'multipart/form-data']) . '
' . Form::input('hidden', 'action', Request::get('a', '')) . '
<table class="formtable">
' . $action_code . '

  <tr>
  <td></td>
  <td>' . Form::input('submit', null, _lang($action_submit), ['accesskey' => 's']) . ' <a href="' . _e($fmanUrl()) . '">' . _lang('global.cancel') . '</a></td>
  </tr>

</table>
' . Form::end('fman-action') . '
</div>
';
        }
    }

    // output

    // menu, action form
    $output .= $message . '
    <a id="top"></a>
    <p class="fman-menu">
    <a href="' . _e($fmanUrl(['a' => 'upload'])) . '">' . _lang('admin.fman.menu.upload') . '</a>
    <a href="' . _e($fmanUrl(['a' => 'edit'])) . '">' . _lang('admin.fman.menu.createfile') . '</a>
    <a href="' . _e($fmanUrl(['a' => 'newfolder'])) . '">' . _lang('admin.fman.menu.createfolder') . '</a>
    ' . ((User::hasPrivilege('admingallery') && User::hasPrivilege('admincontent')) ? '<a href="#" onclick="return Sunlight.admin.fmanAddSelectedToGallery()">' . _lang('admin.fman.menu.addtogallery') . '</a>' : '') . '
    <a href="' . _e($fmanUrl(['dir' => $defdir])) . '">' . _lang('admin.fman.menu.home') . '</a>
    <br class="mobile-only">
    <strong>' . _lang('admin.fman.currentdir') . ':</strong> ' . substr($dir, strlen(SL_ROOT)) . '
    </p>

    ' . $action_code;

    // list
    $output .= '
    ' . Form::start('filelist', ['action' => $fmanUrl()]) . '
    ' . Form::input('hidden', 'action', -1) . '
    ' . Form::input('hidden', 'param', -1) . '
    <div class="horizontal-scroller">
    <table id="fman-list">
    ';

    $highlight = false;

    // directories
    $handle = opendir($dir);
    $items = [];

    while (($item = readdir($handle)) !== false) {
        if ($item != '.' && $item != '..' && is_dir($dir . $item)) {
            $items[] = $item;
        }
    }

    natsort($items);
    $items = array_merge(['..'], $items);
    $dircounter = 0;

    foreach ($items as $item) {
        // directory or parent link
        if ($item == '..') {
            if (($dirhref = User::checkPath($dir . $item, false, true)) === false) {
                continue;
            }
        } else {
            $dirhref = $dir . $item;
        }

        if ($highlight) {
            $hl_class = ' class="hl"';
        } else {
            $hl_class = '';
        }

        $output .= '
        <tr' . $hl_class . '>
        <td class="fman-item" colspan="' . (($item == '..') ? '3' : '2') . '">
            <a href="' . _e($fmanUrl(['dir' => $dirhref])) . '">
                <img src="' . _e(Router::path('admin/public/images/icons/fman/dir.png')) . '" alt="dir" class="icon">' . _e(StringHelper::ellipsis($item, 64, false)) . '
            </a>
        </td>
        ' . (($item != '..') ? '<td class="actions">
            <a class="button" href="' . _e($fmanUrl(['a' => 'delete', 'name' => $encodeFilename($item)])) . '"><img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">' . _lang('global.delete') . '</a>
            <a class="button" href="' . _e($fmanUrl(['a' => 'rename', 'name' => $encodeFilename($item)])) . '"><img src="' . _e(Router::path('admin/public/images/icons/rename.png')) . '" alt="rename" class="icon">' . _lang('admin.fman.rename') . '</a>
        </td>' : '') . "
        </tr>\n";

        $highlight = !$highlight;
        ++$dircounter;
    }

    if ($dircounter !== 0) {
        $output .= '<tr><td class="fman-spacer" colspan="3"></td></tr>';
    }

    // files
    rewinddir($handle);
    $items = [];

    while (($item = readdir($handle)) !== false) {
        if ($item !== '.' && $item != '..' && !is_dir($dir . $item)) {
            $items[] = $item;
        }
    }

    natsort($items);
    $filecounter = 0;
    $sizecounter = 0;

    foreach ($items as $item) {
        ++$filecounter;
        $row_classes = [];

        // icon
        $iteminfo = pathinfo($item);

        if (!isset($iteminfo['extension'])) {
            $iteminfo['extension'] = '';
        }

        $ext = strtolower($iteminfo['extension']);
        $image = false;

        $icon = $extensions[$ext] ?? 'other';
        $image = $icon === 'image';

        $filesize = filesize($dir . $item);

        if ($highlight) {
            $row_classes[] = 'hl';
        }

        if (isset($uploaded[$item])) {
            $row_classes[] = 'fman-uploaded';
        }

        $output .= '
        <tr class="' . implode(' ', $row_classes) . '">
        <td class="fman-item">
            ' . Form::input('checkbox', 'file_' . $filecounter, $encodeFilename($item), ['id' => 'file_' . $filecounter]) . '
            <a href="' . _e(Router::file($dir . $item)) . '" target="_blank"' . ($image ? Extend::buffer('image.lightbox', ['group' => 'fman']) : '') . '>
                <img src="' . _e(Router::path('admin/public/images/icons/fman/' . $icon . '.png')) . '" alt="file" class="icon">'
                . _e(StringHelper::ellipsis($item, 64, false)) . '
            </a>
        </td>
        <td class="fman-size">' . GenericTemplates::renderFileSize($filesize) . '</td>
        <td class="actions">' . (User::checkFilename($item) ?
            '<a class="button" href="' . _e($fmanUrl(['a' => 'delete', 'name' => $encodeFilename($item)])) . '">
                <img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">'
                . _lang('global.delete')
            . '</a>  '
            . '<a class="button" href="' . _e($fmanUrl(['a' => 'rename', 'name' => $encodeFilename($item)])) . '">
                <img src="' . _e(Router::path('admin/public/images/icons/rename.png')) . '" alt="rename" class="icon">'
                . _lang('admin.fman.rename')
            . '</a>  '
            . (($icon === 'editable')
                ? '<a class="button" href="' . _e($fmanUrl(['a' => 'edit', 'name' => $encodeFilename($item)])) . '">'
                . '<img src="' . _e(Router::path('admin/public/images/icons/edit.png')) . '" alt="edit" class="icon">'
                . _lang('admin.fman.edit')
                . '</a>'
                : '')
        : '') . "</td>
        </tr>\n";

        $sizecounter += $filesize;

        $highlight = !$highlight;
    }

    if ($filecounter === 0 && $dircounter === 0) {
        $output .= '<tr><td colspan="3">' . _lang('global.nokit') . "</td></tr>\n";
    }

    $output .= '
    </table>
    </div>
    ' . Form::end('filelist') . '

    <p class="fman-menu">
    <span><strong>' . _lang('admin.fman.filecounter') . ':</strong> ' . _num($filecounter) . ' <small>(' . GenericTemplates::renderFileSize($sizecounter) . ')</small></span>
    <a href="#" onclick="return Sunlight.admin.fmanSelect(' . _num($filecounter) . ', 1)">' . _lang('admin.fman.selectall') . '</a>
    <a href="#" onclick="return Sunlight.admin.fmanSelect(' . _num($filecounter) . ', 2)">' . _lang('admin.fman.deselectall') . '</a>
    <a href="#" onclick="return Sunlight.admin.fmanSelect(' . _num($filecounter) . ', 3)">' . _lang('admin.fman.inverse') . '</a>
    <br class="mobile-only">
    <strong>' . _lang('admin.fman.selected') . ':</strong>&nbsp;&nbsp;
    <a href="#" onclick="return Sunlight.admin.fmanMoveSelected()">' . _lang('admin.fman.selected.move') . '</a>
    <a href="#" onclick="return Sunlight.admin.fmanDeleteSelected()">' . _lang('admin.fman.selected.delete') . '</a>
    <a href="#" onclick="return Sunlight.admin.fmanDownloadSelected()">' . _lang('admin.fman.selected.download') . '</a>
    <a href="#top" class="desktop-only"><span class="big-text">&uarr;</span></a>
    </p>
    ';
}
