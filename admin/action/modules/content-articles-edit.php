<?php

use Sunlight\Admin\Admin;
use Sunlight\Article;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';
$continue = false;

if (isset($_GET['id'], $_GET['returnid'], $_GET['returnpage'])) {
    $id = (int) Request::get('id');
    $returnid = Request::get('returnid');

    if ($returnid != 'load') {
        $returnid = (int) $returnid;
    }

    $returnpage = (int) Request::get('returnpage');
    $query = DB::queryRow('SELECT art.*,cat.slug AS cat_slug FROM ' . DB::table('article') . ' AS art JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1) WHERE art.id=' . $id . Admin::articleAccess('art'));

    if ($query !== false) {
        $read_counter = $query['readnum'];

        if ($returnid == 'load') {
            $returnid = $query['home1'];
        }

        $backlink = Router::admin('content-articles-list', ['query' => ['cat' => $returnid, 'page' => $returnpage]]);
        $actionplus = ['query' => ['id' => $id, 'returnid' => $returnid, 'returnpage' => $returnpage]];
        $submittext = 'global.savechanges';
        $artlink = ' <a href="' . _e(Router::article($query['id'], $query['slug'], $query['cat_slug'])) . '" target="_blank"><img src="' . _e(Router::path('admin/images/icons/loupe.png')) . '" alt="prev"></a>';
        $new = false;
        $continue = true;
    }
} else {
    $backlink = Router::admin('content-articles');
    $actionplus = null;
    $submittext = 'global.create';
    $artlink = '';
    $new = true;
    $id = -1;
    $read_counter = 0;
    $query = [
        'id' => -1,
        'title' => '',
        'slug' => '',
        'description' => '',
        'perex' => '<p></p>',
        'picture_uid' => null,
        'content' => '',
        'author' => User::getId(),
        'home1' => -2,
        'home2' => -1,
        'home3' => -1,
        'time' => time(),
        'visible' => 1,
        'public' => 1,
        'comments' => 1,
        'commentslocked' => 0,
        'showinfo' => 1,
        'confirmed' => 0,
        'rateon' => 1,
        'readnum' => 0,
    ];
    Extend::call('admin.article.default', ['data' => &$query]);

    if (isset($_GET['new_cat'])) {
        $query['home1'] = (int) Request::get('new_cat');
    }

    $continue = true;
}

// save
if (isset($_POST['title'])) {
    $slug = Request::post('slug', '');

    if ($slug === '') {
        $slug = Request::post('title', '');
    }

    $newdata['title'] = Html::cut(_e(Request::post('title', '')), 255);
    $newdata['slug'] = StringManipulator::slugify($slug);
    $newdata['description'] = Html::cut(_e(trim(Request::post('description', ''))), 255);
    $newdata['home1'] = (int) Request::post('home1');
    $newdata['home2'] = (int) Request::post('home2');
    $newdata['home3'] = (int) Request::post('home3');

    if (User::hasPrivilege('adminchangeartauthor')) {
        $newdata['author'] = (int) Request::post('author');
    } else {
        $newdata['author'] = $query['author'];
    }

    $newdata['perex'] = Request::post('perex');
    $newdata['content'] = User::filterContent(Request::post('content'));
    $newdata['public'] = Form::loadCheckbox('public');
    $newdata['visible'] = Form::loadCheckbox('visible');

    if (User::hasPrivilege('adminconfirm') || (User::hasPrivilege('adminautoconfirm') && User::equals($newdata['author']))) {
        $newdata['confirmed'] = Form::loadCheckbox('confirmed');
    } else {
        $newdata['confirmed'] = $query['confirmed'];
    }

    $newdata['comments'] = Form::loadCheckbox('comments');
    $newdata['commentslocked'] = Form::loadCheckbox('commentslocked');
    $newdata['rateon'] = Form::loadCheckbox('rateon');
    $newdata['showinfo'] = Form::loadCheckbox('showinfo');
    $newdata['resetrate'] = Form::loadCheckbox('resetrate');
    $newdata['delcomments'] = Form::loadCheckbox('delcomments');
    $newdata['resetread'] = Form::loadCheckbox('resetread');
    $newdata['time'] = Form::loadTime('time', $query['time']);

    // check variables
    $error_log = [];

    // title
    if ($newdata['title'] === '') {
        $error_log[] = _lang('admin.content.articles.edit.error1');
    }

    // slug
    if ($newdata['slug'] === '') {
        $error_log[] = _lang('admin.content.form.slug.empty');
    }

    // category
    $homechecks = ['home1', 'home2', 'home2'];

    foreach ($homechecks as $homecheck) {
        if ($newdata[$homecheck] != -1 || $homecheck == 'home1') {
            if (DB::count('page', 'type=' . Page::CATEGORY . ' AND id=' . DB::val($newdata[$homecheck])) === 0) {
                $error_log[] = _lang('admin.content.articles.edit.error2');
            }
        }
    }

    // remove duplicate categories
    if ($newdata['home1'] == $newdata['home2']) {
        $newdata['home2'] = -1;
    }

    if ($newdata['home2'] == $newdata['home3'] || $newdata['home1'] == $newdata['home3']) {
        $newdata['home3'] = -1;
    }

    // author
    if (
        DB::result(DB::query(
            'SELECT COUNT(*) FROM ' . DB::table('user')
            . ' WHERE id=' . DB::val($newdata['author'])
            . ' AND ('
                . 'id=' . User::getId()
                . ' OR (SELECT level FROM ' . DB::table('user_group') . ' WHERE id=' . DB::table('user') . '.group_id)<' . User::getLevel()
            . ')'
        )) == 0
    ) {
        $error_log[] = _lang('admin.content.articles.edit.error3');
    }

    // image
    $newdata['picture_uid'] = $query['picture_uid'];

    if (empty($error_log) && isset($_FILES['picture']) && is_uploaded_file($_FILES['picture']['tmp_name'])) {
        // prepare resize options
        $picOpts = [
            'file_path' => $_FILES['picture']['tmp_name'],
            'file_name' => $_FILES['picture']['name'],
            'target_dir' => 'images/articles/',
            'target_format' => 'jpg',
            'target_partitions' => 1,
            'resize' => [
                'mode' => 'fit',
                'keep_smaller' => true,
                'x' => Settings::get('article_pic_w'),
                'y' => Settings::get('article_pic_h'),
            ],
        ];
        Extend::call('admin.article.picture', ['opts' => &$picOpts]);

        // upload image
        $pic_uid = Article::uploadImage($_FILES['picture']['tmp_name'], $_FILES['picture']['name'], $pic_err);

        if ($pic_uid !== null) {
            // success
            if (isset($query['picture_uid'])) {
                // delete old image
                Article::removeImage($query['picture_uid']);
            }

            $newdata['picture_uid'] = $pic_uid;
        } else {
            // error
            $error_log[] = Message::prefix(_lang('admin.content.form.picture'), $pic_err->getUserFriendlyMessage());
        }
    } elseif (isset($query['picture_uid']) && Form::loadCheckbox('picture-delete')) {
        // remove image
        Article::removeImage($query['picture_uid']);
        $newdata['picture_uid'] = null;
    }

    // save changes
    if (empty($error_log)) {
        $changeset = [
            'title' => $newdata['title'],
            'slug' => $newdata['slug'],
            'description' => $newdata['description'],
            'home1' => $newdata['home1'],
            'home2' => $newdata['home2'],
            'home3' => $newdata['home3'],
            'author' => $newdata['author'],
            'perex' => $newdata['perex'],
            'picture_uid' => $newdata['picture_uid'],
            'content' => $newdata['content'],
            'public' => $newdata['public'],
            'visible' => $newdata['visible'],
            'confirmed' => $newdata['confirmed'],
            'comments' => $newdata['comments'],
            'commentslocked' => $newdata['commentslocked'],
            'rateon' => $newdata['rateon'],
            'showinfo' => $newdata['showinfo'],
            'time' => $newdata['time'],
        ];

        if ($new) {
            $action = 'new';
            $changeset += [
                'readnum' => 0,
                'ratenum' => 0,
                'ratesum' => 0,
            ];
        } else {
            $action = 'edit';
        }

        Extend::call('admin.article.' . $action . '.before', [
            'id' => $id,
            'article' => $new ? null : $query,
            'changeset' => &$changeset,
        ]);

        if (!$new) {
            // update
            DB::update('article', 'id=' . $id, $changeset);

            // delete comments
            if ($newdata['delcomments'] == 1) {
                DB::delete('post', 'type=' . Post::ARTICLE_COMMENT . ' AND home=' . $id);
            }

            // reset read counter
            if ($newdata['resetread'] == 1) {
                DB::update('article', 'id=' . $id, ['readnum' => 0]);
            }

            // reset rating
            if ($newdata['resetrate'] == 1) {
                DB::update('article', 'id=' . $id, [
                    'ratenum' => 0,
                    'ratesum' => 0
                ]);
                DB::delete('iplog', 'type=' . IpLog::ARTICLE_RATED . ' AND var=' . $id);
            }

            // redirect
            $_admin->redirect(Router::admin('content-articles-edit', ['query' => ['id' => $id, 'saved' => 1, 'returnid' => $returnid, 'returnpage' => $returnpage]]));
        } else {
            // insert
            $id = DB::insert('article', $changeset, true);

            // redirect
            $_admin->redirect(Router::admin('content-articles-edit', ['query' => ['id' => $id, 'created' => 1, 'returnid' => $newdata['home1'], 'returnpage' => 1]]));
        }

        Extend::call('admin.article.' . $action, [
            'id' => $id,
            'article' => $query,
            'changeset' => &$changeset,
        ]);

        return;
    }

    $message = Message::list($error_log);
    $query = $newdata + $query;
}

// output
if ($continue) {
    // message
    if (isset($_GET['saved'])) {
        $message = Message::ok(_lang('global.saved') . ' <small>(' . GenericTemplates::renderTime(time()) . ')</small>', true);
    }

    if (isset($_GET['created'])) {
        $message = Message::ok(_lang('global.created'));
    }

    // calculate rating
    if (!$new) {
        if ($query['ratenum'] != 0) {
            $rate = DB::result(DB::query('SELECT ROUND(ratesum/ratenum) FROM ' . DB::table('article') . ' WHERE id=' . $query['id'])) . '%, ' . $query['ratenum'] . 'x';
        } else {
            $rate = _lang('article.rate.nodata');
        }
    } else {
        $rate = '';
    }

    // image
    $picture = '';

    if (isset($query['picture_uid'])) {
        $picture .= '<img src="' . _e(Router::file(Article::getImagePath($query['picture_uid']))) . '" alt="article picture" id="is-picture-file">
<label id="is-picture-delete"><input type="checkbox" name="picture-delete" value="1"> ' . _lang('global.delete') . '</label>';
    } else {
        $picture .= '<img src="' . _e(Router::path('admin/images/art-no-pic.png')) . "\" alt=\"no picture\" id=\"is-picture-file\">\n";
    }

    $picture .= "<input type=\"file\" name=\"picture\" id=\"is-picture-upload\">\n";

    // content editor
    $editor = Extend::buffer('admin.article.editor');

    if ($editor === '') {
        // default implementation
        $editor = '<textarea name="content" rows="25" cols="94" class="areabig editor">' . _e($query['content']) . '</textarea>';
    }

    // form
    $output .= Admin::backlink($backlink) . '
<h1>' . _lang('admin.content.articles.edit.title') . '</h1>
' . $message . '

' . (($new && !User::hasPrivilege('adminautoconfirm')) ? Admin::note(_lang('admin.content.articles.edit.newconfnote')) : '') . '
' . ((!$new && $query['confirmed'] != 1) ? Admin::note(_lang('admin.content.articles.edit.confnote')) : '') . '

' . ((!$new && DB::count('article', 'id!=' . DB::val($query['id']) . ' AND home1=' . DB::val($query['home1']) . ' AND slug=' . DB::val($query['slug'])) !== 0) ? Message::warning(_lang('admin.content.form.slug.collision')) : '') . '

<form class="cform" action="' . _e(Router::admin('content-articles-edit', $actionplus)) . '" method="post" enctype="multipart/form-data" name="artform">
    <table class="formtable edittable">
        <tbody>
            <tr class="valign-top">
                <td class="contenttable-box main-box">
                    <table>
                        <tbody>
                            <tr>
                                <th>' . _lang('article.category') . '</th>
                                <td>'
                                    . Admin::pageSelect('home1', ['type' => Page::CATEGORY, 'selected' => $query['home1']])
                                    . Admin::pageSelect('home2', ['type' => Page::CATEGORY, 'selected' => $query['home2'], 'empty_item' => _lang('admin.content.form.category.none')])
                                    . Admin::pageSelect('home3', ['type' => Page::CATEGORY, 'selected' => $query['home3'], 'empty_item' => _lang('admin.content.form.category.none')])
                                    . '
                                </td>
                            </tr>
                            <tr>
                                <th>' . _lang('admin.content.form.title') . '</th>
                                <td><input type="text" name="title" value="' . $query['title'] . '" class="inputmax"></td>
                            </tr>
                            <tr>
                                <th>' . _lang('admin.content.form.slug') . '</th>
                                <td><input type="text" name="slug" value="' . $query['slug'] . '" maxlength="255" class="inputmax"></td>
                            </tr>
                            <tr>
                                <th>' . _lang('admin.content.form.description') . '</th>
                                <td><input type="text" name="description" value="' . $query['description'] . '" maxlength="255" class="inputmax"></td>
                            </tr>
                            <tr class="valign-top">
                                <th>' . _lang('admin.content.form.perex') . '</th>
                                <td><textarea name="perex" rows="9" cols="94" class="areabigperex editor" data-editor-mode="lite">' . _e($query['perex']) . '</textarea></td>
                            </tr>
                            <tr class="valign-top">
                                <th>' . _lang('admin.content.form.content') . $artlink . '</th>
                                <td>' . $editor . '</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>' . _lang('article.posted') . '</th>
                                <td>' . Form::editTime('time', $query['time'], true, $new) . '</td>
                            </tr>
                            ' . Extend::buffer('admin.article.form', ['article' => $query]) . '
                            <tr>
                                <td></td>
                                <td id="ae-lastrow"><br><input type="submit" class="button bigger" value="' . _lang($submittext) . '" accesskey="s">'
                                . (!$new ? '
                                    <span class="customsettings">
                                        <a href="' . _e(Router::admin('content-articles-delete', ['query' => ['id' => $query['id'], 'returnid' => $query['home1'], 'returnpage' => 1]])) . '">
                                            <span><img src="' . _e(Router::path('admin/images/icons/delete.png')) . '" alt="del" class="icon">' . _lang('global.delete') . '</span>
                                        </a>
                                    </span>
                                    <span class="customsettings">
                                        <small>' . _lang('admin.content.form.thisid') . ' ' . $query['id'] . '</small>
                                    </span>
                                ' : '')
                                . '</td>
                            </tr>
                       </tfoot>     
                    </table>    
                </td> 
                <td class="contenttable-box">
                    <div id="settingseditform">
                        ' . Extend::buffer('admin.article.settings.before', ['article' => $query]) . '
                        <fieldset>
                            <legend>' . _lang('admin.content.form.picture') . '</legend>
                            <div id="is-picture">' . $picture . '</div>
                        </fieldset>'

                        . (User::hasPrivilege('adminchangeartauthor')
                            ? '<fieldset>
                                <legend>' . _lang('article.author') . '</legend>'
                                . Admin::userSelect('author', ['selected' => $query['author'], 'group_cond' => 'adminart=1', 'class' => 'inputmax'])
                                . '</fieldset>'
                            : '')
                        
                        . '<fieldset>
                            <legend>' . _lang('admin.content.form.settings') . '</legend>
                            <table>
                                <tbody>
                                    <tr><td><label><input type="checkbox" name="public" value="1"' . Form::activateCheckbox($query['public']) . '> ' . _lang('admin.content.form.public') . '</label></td></tr>
                                    <tr><td><label><input type="checkbox" name="visible" value="1"' . Form::activateCheckbox($query['visible']) . '> ' . _lang('admin.content.form.visible') . '</label></td></tr>
                                    ' . ((User::hasPrivilege('adminconfirm') || (User::hasPrivilege('adminautoconfirm') && User::equals($query['author'])))
                                        ? '<tr><td><label><input type="checkbox" name="confirmed" value="1"' . Form::activateCheckbox($query['confirmed']) . '> ' . _lang('admin.content.form.confirmed') . '</label></td></tr>'
                                        : '') . '
                                    <tr><td><label><input type="checkbox" name="comments" value="1"' . Form::activateCheckbox($query['comments']) . '> ' . _lang('admin.content.form.comments') . '</label></td></tr>
                                    <tr><td><label><input type="checkbox" name="commentslocked" value="1"' . Form::activateCheckbox($query['commentslocked']) . '> ' . _lang('admin.content.form.commentslocked') . '</label></td></tr>
                                    <tr><td><label><input type="checkbox" name="rateon" value="1"' . Form::activateCheckbox($query['rateon']) . '> ' . _lang('admin.content.form.artrate') . '</label></td></tr>
                                    <tr><td><label><input type="checkbox" name="showinfo" value="1"' . Form::activateCheckbox($query['showinfo']) . '> ' . _lang('admin.content.form.showinfo') . '</label></td></tr>
                                    ' . (!$new ? '<tr><td><label><input type="checkbox" name="resetrate" value="1"> ' . _lang('admin.content.form.resetartrate') . ' <small>(' . $rate . ')</small></label></td></tr>' : '') . '
                                    ' . (!$new ? '<tr><td><label><input type="checkbox" name="delcomments" value="1"> ' . _lang('admin.content.form.delcomments') . ' <small>(' . DB::count('post', 'home=' . DB::val($query['id']) . ' AND type=' . Post::ARTICLE_COMMENT) . ')</small></label></td></tr>' : '') . '
                                    ' . (!$new ? '<tr><td><label><input type="checkbox" name="resetread" value="1"> ' . _lang('admin.content.form.resetartread') . ' <small>(' . $read_counter . ')</small></label></td></tr>' : '') . '
                                </tbody>
                            </table>
                        </fieldset>
                        ' . Extend::buffer('admin.article.settings.after', ['article' => $query]) . '
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
' . Xsrf::getInput() . '</form>

';
} else {
    $output .=
        Admin::backlink(Router::admin('content-articles'))
        . '<h1>' . _lang('admin.content.articles.edit.title') . "</h1>\n"
        . Message::error(_lang('global.badinput'));
}
