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
' . Form::start('uploadform', ['action' => Router::admin('content-manageimgs', ['query' => ['g' => $galid]]), 'enctype' => 'multipart/form-data']) . '
    <p>' . _lang('admin.content.manageimgs.upload.text', ['%w%' => Settings::get('galuploadresize_w'), '%h%' => Settings::get('galuploadresize_h')]) . '</p>
    ' . Form::input('hidden', 'xaction', '7') . '
    <div id="fmanFiles">' . Form::input('file', 'uf0[]', null, ['multiple' => true]) . ' <a href="#" onclick="return Sunlight.admin.fmanAddFile();">' . _lang('admin.fman.upload.addfile') . '</a></div>
    <div class="hr"><hr></div>
    <p>
        ' . Form::input('submit', null, _lang('admin.content.manageimgs.upload.submit')) . '
        <label>' . Form::input('checkbox', 'moveords', '1', ['checked' => true]) . ' ' . _lang('admin.content.manageimgs.moveords') . '</label>'
        . Environment::renderUploadLimit() . '
    </p>
' . Form::end('uploadform') . '
</fieldset>

<fieldset class="hs_fieldset">
<legend>' . _lang('admin.content.manageimgs.insert') . '  <small>(' . _lang('admin.content.manageimgs.insert.tip') . ')</small></legend>
' . Form::start('addform', ['action' => Router::admin('content-manageimgs', ['query' => ['g' => $galid]])]) . '
' . Form::input('hidden', 'xaction', '1') . '

<table>
<tr>
<th>' . _lang('admin.content.form.title') . '</th>
<td>' . Form::input('text', 'title', null, ['class' => 'inputmedium', 'maxlength' => 255]) . '</td>
</tr>

<tr>
<th>' . _lang('admin.content.form.ord') . '</th>
<td>
    ' . Form::input('number', 'ord', null, ['class' => 'inputsmall', 'disabled' => true]) . '
    <label>' . Form::input('checkbox', 'moveords', '1', ['checked' => true, 'onclick' => 'Sunlight.toggleFormField(this.checked, \'addform\', \'ord\');']) . ' ' . _lang('admin.content.manageimgs.moveords') . '</label>
</td>
</tr>

<tr>
<th>' . _lang('admin.content.manageimgs.prev') . '</th>
<td>
    ' . Form::input('text', 'prev', null, ['class' => 'inputsmall', 'disabled' => true]) . '
    <label>' . Form::input('checkbox', 'autoprev', '1', ['checked' => true, 'onclick' => 'Sunlight.toggleFormField(this.checked, \'addform\', \'prev\');']) . ' ' . _lang('admin.content.manageimgs.autoprev') . '</label>
</td>
</tr>

<tr>
<th>' . _lang('admin.content.manageimgs.full') . '</th>
<td>' . Form::input('text', 'full', null, ['class' => 'inputmedium']) . '</td>
</tr>

<tr>
<td></td>
<td>' . Form::input('submit', null, _lang('global.insert')) . '</td>
</tr>

</table>

' . Form::end('addform') . '
</fieldset>

';

    // images
    $output .= '
<fieldset>
<legend>' . _lang('admin.content.manageimgs.current') . '</legend>
' . Form::start('editform', ['action' => Router::admin('content-manageimgs', ['query' => ['g' => $galid]])]) . '
' . Form::input('hidden', 'xaction', '4') . '

' . Form::input('submit', null, _lang('admin.content.manageimgs.savechanges'), ['class' => 'gallery-savebutton']) . '
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
<td>' . Form::input('text', 'i' . $image['id'] . '_title', $image['title'], ['class' => 'max-width', 'maxlength' => 255], false) . '</td>
</tr>

<tr class="image-order-row">
<th>' . _lang('admin.content.form.ord') . '</th>
<td>' . Form::input('text', 'i' . $image['id'] . '_ord', $image['ord'], ['class' => 'max-width']) . '</td>
</tr>

' . (!$image['in_storage'] ? '<tr>
<th>' . _lang('admin.content.manageimgs.prev') . '</th>
<td>
    ' . Form::input('hidden', 'i' . $image['id'] . '_prevtrigger', '1') . '
    ' . Form::input('text', 'i' . $image['id'] . '_prev', $image['prev'], ['class' => 'inputsmall', 'disabled' => ($image['prev'] != '')]) . '
    <label>' . Form::input('checkbox', 'i' . $image['id'] . '_autoprev', '1', ['checked' => ($image['prev'] == ''), 'onclick' => 'Sunlight.toggleFormField(checked, \'editform\', \'i' . $image['id'] . '_prev\');']) . ' ' . _lang('admin.content.manageimgs.autoprev') . '</label>
</td>
</tr>

<tr>
<th>' . _lang('admin.content.manageimgs.full') . '</th>
<td>' . Form::input('text', 'i' . $image['id'] . '_full', $image['full'], ['class' => 'max-width']) . '</td>
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
' . Form::input('submit', null, _lang('admin.content.manageimgs.savechanges'), ['class' => 'gallery-savebutton', 'accesskey' => 's']);
    } else {
        $output .= '<p>' . _lang('global.nokit') . '</p>';
    }

    $output .= '
' . Form::end('editform') . '
</fieldset>

<table class="max-width">
<tr class="valign-top">

<td class="half-width">
  <fieldset class="hs_fieldset">
  <legend>' . _lang('admin.content.manageimgs.moveimgs') . '</legend>

  ' . Form::start('moveform', ['class' => 'cform', 'action' => Router::admin('content-manageimgs', ['query' => ['g' => $galid]])]) . '
  ' . Form::input('hidden', 'newhome', '5') . '
  ' . Admin::pageSelect('newhome', ['type' => Page::GALLERY]) . ' ' . Form::input('submit', null, _lang('global.do'), ['class' => 'button', 'onclick' => 'return Sunlight.confirm();']) . '<br><br>
  <label>' . Form::input('checkbox', 'moveords', '1', ['checked' => true]) . ' ' . _lang('admin.content.manageimgs.moveords') . '</label>
  ' . Form::end('moveform') . '

  </fieldset>
</td>

<td>
  <fieldset class="hs_fieldset">
  <legend>' . _lang('admin.content.manageimgs.delimgs') . '</legend>

  ' . Form::start('delform', ['class' => 'cform', 'action' => Router::admin('content-manageimgs', ['query' => ['g' => $galid]])]) . '
  ' . Form::input('hidden', 'newhome', '6') . '
  <label>' . Form::input('checkbox', 'confirm', '1') . ' ' . _lang('admin.content.manageimgs.delimgs.confirm') . '</label> ' . Form::input('submit', null, _lang('global.do'), ['class' => 'button', 'onclick' => 'return Sunlight.confirm();']) . '
  ' . Form::end('delform') . '

  </fieldset>
</td>

</tr>
</table>

';
} else {
    $output .= Message::error(_lang('global.badinput'));
}
