<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Gallery;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Environment;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';
$continue = false;

if (isset($_GET['g'])) {
    $galid = (int) Request::get('g');
    $galdata = DB::queryRow('SELECT title,var2,var3,var4 FROM ' . DB::table('page') . ' WHERE id=' . $galid . ' AND type=' . Page::GALLERY);

    if ($galdata !== false) {
        if ($galdata['var2'] === null) {
            $galdata['var2'] = Settings::get('galdefault_per_page');
        }

        if ($galdata['var3'] === null) {
            $galdata['var3'] = Settings::get('galdefault_thumb_h');
        }

        if ($galdata['var4'] === null) {
            $galdata['var4'] = Settings::get('galdefault_thumb_w');
        }

        $continue = true;
    }
}

// action
if (isset($_POST['xaction']) && $continue) {
    switch (Request::post('xaction')) {
        // insert image
        case 1:
            // load base vars
            $title = Html::cut(_e(trim(Request::post('title', ''))), 255);

            if (!Form::loadCheckbox('autoprev')) {
                $prev = Html::cut(_e(Request::post('prev', '')), 255);
            } else {
                $prev = '';
            }

            $full = Html::cut(_e(Request::post('full', '')), 255);

            // load order
            if (Form::loadCheckbox('moveords')) {
                $smallerord = DB::queryRow('SELECT ord FROM ' . DB::table('gallery_image') . ' WHERE home=' . $galid . ' ORDER BY ord LIMIT 1');

                if ($smallerord !== false) {
                    $ord = $smallerord['ord'];
                } else {
                    $ord = 1;
                }

                DB::update('gallery_image', 'home=' . $galid, ['ord' => DB::raw('ord+1')], null);
            } else {
                $ord = floatval(Request::post('ord'));
            }

            // check and insert
            if ($full != '') {
                DB::insert('gallery_image', [
                    'home' => $galid,
                    'ord' => $ord,
                    'title' => $title,
                    'prev' => $prev,
                    'full' => $full
                ]);
                $message .= Message::ok(_lang('global.inserted'));
            } else {
                $message .= Message::warning(_lang('admin.content.manageimgs.insert.error'));
            }

            break;

        // update images
        case 4:
            $lastid = -1;
            $sql = '';

            foreach ($_POST as $var => $val) {
                if ($var == 'xaction') {
                    continue;
                }

                $var = explode('_', $var);

                if (count($var) == 2) {
                    $id = (int) substr($var[0], 1);
                    $var = $var[1];

                    if ($lastid == -1) {
                        $lastid = $id;
                    }

                    $quotes = true;
                    $skip = false;

                    switch ($var) {
                        case 'title':
                            $val = _e($val);
                            break;
                        case 'full':
                            $val = 'IF(in_storage,full,' . DB::val(_e($val)) . ')';
                            $quotes = false;
                            break;
                        case 'prevtrigger':
                            $var = 'prev';

                            if (!Form::loadCheckbox('i' . $id . '_autoprev')) {
                                $val = Html::cut(_e(Request::post('i' . $id . '_prev', '')), 255);
                            } else {
                                $val = '';
                            }
                            break;
                        case 'ord':
                            $val = (int) $val;
                            $quotes = false;
                            break;
                        default:
                            $skip = true;
                            break;
                    }

                    // save each image
                    if (!$skip) {
                        if ($lastid != $id) {
                            $sql = trim($sql, ',');
                            DB::query('UPDATE ' . DB::table('gallery_image') . ' SET ' . $sql . ' WHERE id=' . $lastid . ' AND home=' . $galid);
                            $sql = '';
                            $lastid = $id;
                        }

                        if ($sql !== '') {
                            $sql .= ',';
                        }

                        $sql .= $var . '=';

                        if ($quotes) {
                            $sql .= DB::val($val);
                        } else {
                            $sql .= $val;
                        }
                    }
                }
            }

            // save last (or only) image
            if ($sql != '') {
                DB::query('UPDATE ' . DB::table('gallery_image') . ' SET ' . $sql . ' WHERE id=' . $id . ' AND home=' . $galid);
            }

            $message .= Message::ok(_lang('global.saved'));
            break;

        // move images
        case 5:
            $newhome = (int) Request::post('newhome');

            if ($newhome != $galid) {
                if (DB::count('page', 'id=' . DB::val($newhome) . ' AND type=' . Page::GALLERY . ' AND level<=' . User::getLevel()) !== 0) {
                    if (DB::count('gallery_image', 'home=' . DB::val($galid)) !== 0) {
                        // move order numbers in the target gallery
                        $moveords = Form::loadCheckbox('moveords');

                        if ($moveords) {
                            // get the highest order number in this gallery
                            $greatestord = DB::queryRow('SELECT ord FROM ' . DB::table('gallery_image') . ' WHERE home=' . $galid . ' ORDER BY ord DESC LIMIT 1');
                            $greatestord = $greatestord['ord'];

                            DB::update('gallery_image', 'home=' . $newhome, ['ord' => DB::raw('ord+' . $greatestord)], null);
                        }

                        // move images
                        DB::update('gallery_image', 'home=' . $galid, ['home' => $newhome], null);
                        $message .= Message::ok(_lang('global.done'));
                    } else {
                        $message .= Message::warning(_lang('admin.content.manageimgs.moveimgs.nokit'));
                    }
                } else {
                    $message .= Message::warning(_lang('global.badinput'));
                }
            } else {
                $message .= Message::warning(_lang('admin.content.manageimgs.moveimgs.samegal'));
            }
            break;

        // remove all images
        case 6:
            if (Form::loadCheckbox('confirm')) {
                Admin::deleteGalleryStorage('home=' . $galid);
                DB::delete('gallery_image', 'home=' . $galid);
                $message .= Message::ok(_lang('global.done'));
            }
            break;

        // upload images
        case 7:
            $done = [];
            $total = 0;
            $storage_dir = 'images/galleries/' . $galid . '/';

            // process uploads
            foreach ($_FILES as $file) {
                if (!is_array($file['name'])) {
                    continue;
                }

                for ($i = 0; isset($file['name'][$i]); ++$i) {
                    ++$total;

                    // check file
                    if ($file['error'][$i] != 0 || !is_uploaded_file($file['tmp_name'][$i])) {
                        continue;
                    }

                    // process
                    $imagePath = Gallery::uploadImage($file['tmp_name'][$i], $file['name'][$i], $storage_dir, $imageErr);

                    if ($imagePath === null) {
                        $message .= Message::error($imageErr->getUserFriendlyMessage());
                        continue;
                    }

                    $done[] = $imagePath;
                }
            }

            // save to database
            if (!empty($done)) {
                // get order number
                if (isset($_POST['moveords'])) {
                    // move
                    $ord = 0;
                    DB::update('gallery_image', 'home=' . $galid, ['ord' => DB::raw('ord+' . count($done))], null);
                } else {
                    // get max + 1
                    $ord = DB::queryRow('SELECT ord FROM ' . DB::table('gallery_image') . ' WHERE home=' . $galid . ' ORDER BY ord DESC LIMIT 1');
                    $ord = $ord['ord'] + 1;
                }

                // query
                $insertdata = [];

                foreach ($done as $path) {
                    $insertdata[] = [
                        'home' => $galid,
                        'ord' => $ord,
                        'title' => '',
                        'prev' => '',
                        'full' => $path,
                        'in_storage' => 1
                    ];
                    ++$ord;
                }

                DB::insertMulti('gallery_image', $insertdata);
            }

            // message
            $done = count($done);
            $message .= Message::render(
                ($done === $total) ? Message::OK : Message::WARNING,
                _lang('admin.content.manageimgs.upload.msg', ['%done%' => _num($done), '%total%' => _num($total)])
            );
            break;
    }
}

// remove image
if (isset($_GET['del']) && Xsrf::check(true) && $continue) {
    $del = (int) Request::get('del');
    Admin::deleteGalleryStorage('id=' . $del . ' AND home=' . $galid);
    DB::delete('gallery_image', 'id=' . $del . ' AND home=' . $galid);

    if (DB::affectedRows() === 1) {
        $message .= Message::ok(_lang('global.done'));
    }
}

// output
if ($continue) {
    $output .= Admin::backlink(Router::admin('content-editgallery', ['query' => ['id' => $galid]])) . '
<h1>' . _lang('admin.content.manageimgs.title') . '</h1>
<p class="bborder">' . _lang('admin.content.manageimgs.p', ['%galtitle%' => $galdata['title']]) . '</p>

' . $message . '

<fieldset>
<legend>' . _lang('admin.content.manageimgs.upload') . '</legend>
<form action="' . _e(Router::admin('content-manageimgs', ['query' => ['g' => $galid]])) . '" method="post" enctype="multipart/form-data">
    <p>' . _lang('admin.content.manageimgs.upload.text', ['%w%' => Settings::get('galuploadresize_w'), '%h%' => Settings::get('galuploadresize_h')]) . '</p>
    <input type="hidden" name="xaction" value="7">
    <div id="fmanFiles"><input type="file" name="uf0[]" multiple> <a href="#" onclick="return Sunlight.admin.fmanAddFile();">' . _lang('admin.fman.upload.addfile') . '</a></div>
    <div class="hr"><hr></div>
    <p>
        <input type="submit" value="' . _lang('admin.content.manageimgs.upload.submit') . '">
        <label><input type="checkbox" value="1" name="moveords" checked> ' . _lang('admin.content.manageimgs.moveords') . '</label>'
        . Environment::renderUploadLimit() . '
    </p>
' . Xsrf::getInput() . '</form>
</fieldset>

<fieldset class="hs_fieldset">
<legend>' . _lang('admin.content.manageimgs.insert') . '  <small>(' . _lang('admin.content.manageimgs.insert.tip') . ')</small></legend>
<form action="' . _e(Router::admin('content-manageimgs', ['query' => ['g' => $galid]])) . '" method="post" name="addform">
<input type="hidden" name="xaction" value="1">

<table>
<tr>
<th>' . _lang('admin.content.form.title') . '</th>
<td><input type="text" name="title" class="inputmedium" maxlength="255"></td>
</tr>

<tr>
<th>' . _lang('admin.content.form.ord') . '</th>
<td>
    <input type="number" name="ord" class="inputsmall" disabled>
    <label><input type="checkbox" name="moveords" value="1" checked onclick="Sunlight.toggleFormField(this.checked, \'addform\', \'ord\');"> ' . _lang('admin.content.manageimgs.moveords') . '</label>
</td>
</tr>

<tr>
<th>' . _lang('admin.content.manageimgs.prev') . '</th>
<td>
    <input type="text" name="prev" class="inputsmall" disabled>
    <label><input type="checkbox" name="autoprev" value="1" checked onclick="Sunlight.toggleFormField(this.checked, \'addform\', \'prev\');"> ' . _lang('admin.content.manageimgs.autoprev') . '</label>
</td>
</tr>

<tr>
<th>' . _lang('admin.content.manageimgs.full') . '</th>
<td><input type="text" name="full" class="inputmedium"></td>
</tr>

<tr>
<td></td>
<td><input type="submit" value="' . _lang('global.insert') . '"></td>
</tr>

</table>

' . Xsrf::getInput() . '</form>
</fieldset>

';

    // images
    $output .= '
<fieldset>
<legend>' . _lang('admin.content.manageimgs.current') . '</legend>
<form action="' . _e(Router::admin('content-manageimgs', ['query' => ['g' => $galid]])) . '" method="post" name="editform">
<input type="hidden" name="xaction" value="4">

<input type="submit" value="' . _lang('admin.content.manageimgs.savechanges') . '" class="gallery-savebutton">
<div class="cleaner"></div>';

    // list
    $images = DB::query('SELECT * FROM ' . DB::table('gallery_image') . ' WHERE home=' . $galid . ' ORDER BY ord');
    $images_forms = [];

    if (DB::size($images) != 0) {
        $output .= '<div
    id="gallery-edit"
    class="sortable"
    data-placeholder="false"
    data-input-selector="tr.image-order-row input"
    data-hide="tr.image-order-row"
    data-cancel="input, a"
    data-auto-grid="true"
    data-tolerance="pointer"
>';

        $resize_options = ['w' => $galdata['var4'], 'h' => $galdata['var3']];

        while ($image = DB::row($images)) {
            $preview = Gallery::renderImage($image, 'admin', $resize_options);

            $output .= '
<div class="gallery-edit-image">
<table>

<tr>
<th>' . _lang('admin.content.form.title') . '</th>
<td><input type="text" name="i' . $image['id'] . '_title" class="max-width" value="' . $image['title'] . '" maxlength="255"></td>
</tr>

<tr class="image-order-row">
<th>' . _lang('admin.content.form.ord') . '</th>
<td><input type="text" name="i' . $image['id'] . '_ord" class="max-width" value="' . $image['ord'] . '"></td>
</tr>

' . (!$image['in_storage'] ? '<tr>
<th>' . _lang('admin.content.manageimgs.prev') . '</th>
<td>
    <input type="hidden" name="i' . $image['id'] . '_prevtrigger" value="1">
    <input type="text" name="i' . $image['id'] . '_prev" class="inputsmall" value="' . $image['prev'] . '"' . Form::disableInputUnless($image['prev'] != '') . '>
    <label><input type="checkbox" name="i' . $image['id'] . '_autoprev" value="1" onclick="Sunlight.toggleFormField(checked, \'editform\', \'i' . $image['id'] . '_prev\');"' . Form::activateCheckbox($image['prev'] == '') . '>' . _lang('admin.content.manageimgs.autoprev') . '</label>
</td>
</tr>

<tr>
<th>' . _lang('admin.content.manageimgs.full') . '</th>
<td><input type="text" name="i' . $image['id'] . '_full" class="max-width" value="' . $image['full'] . '"></td>
</tr>' : '') . '

<tr class="valign-top">
<th>' . _lang('global.preview') . '</th>
<td>' . $preview . '<br><br>
    <a class="button" href="' . _e(Xsrf::addToUrl(Router::admin('content-manageimgs', ['query' => ['g' => $galid, 'del' => $image['id']]]))) . '" onclick="return Sunlight.confirm();">
        <img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">'
        . _lang('admin.content.manageimgs.delete')
    . '</a>
</td>
</tr>

</table>
</div>
';
        }

        $output .= '
</div>
<div class="cleaner"></div>
<input type="submit" value="' . _lang('admin.content.manageimgs.savechanges') . '" class="gallery-savebutton" accesskey="s">';
    } else {
        $output .= '<p>' . _lang('global.nokit') . '</p>';
    }

    $output .= '
' . Xsrf::getInput() . '</form>
</fieldset>

<table class="max-width">
<tr class="valign-top">

<td class="half-width">
  <fieldset class="hs_fieldset">
  <legend>' . _lang('admin.content.manageimgs.moveimgs') . '</legend>

  <form class="cform" action="' . _e(Router::admin('content-manageimgs', ['query' => ['g' => $galid]])) . '" method="post">
  <input type="hidden" name="xaction" value="5">
  ' . Admin::pageSelect('newhome', ['type' => Page::GALLERY]) . ' <input class="button" type="submit" value="' . _lang('global.do') . '" onclick="return Sunlight.confirm();"><br><br>
  <label><input type="checkbox" name="moveords" value="1" checked> ' . _lang('admin.content.manageimgs.moveords') . '</label>
  ' . Xsrf::getInput() . '</form>

  </fieldset>
</td>

<td>
  <fieldset class="hs_fieldset">
  <legend>' . _lang('admin.content.manageimgs.delimgs') . '</legend>

  <form class="cform" action="' . _e(Router::admin('content-manageimgs', ['query' => ['g' => $galid]])) . '" method="post">
  <input type="hidden" name="xaction" value="6">
  <label><input type="checkbox" name="confirm" value="1"> ' . _lang('admin.content.manageimgs.delimgs.confirm') . '</label> <input class="button" type="submit" value="' . _lang('global.do') . '" onclick="return Sunlight.confirm();">
  ' . Xsrf::getInput() . '</form>

  </fieldset>
</td>

</tr>
</table>

';
} else {
    $output .= Message::error(_lang('global.badinput'));
}
