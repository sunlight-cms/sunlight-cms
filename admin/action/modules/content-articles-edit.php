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
use Sunlight\Search\FulltextContentBuilder;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;

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
    $query = DB::queryRow('SELECT art.*,cat.slug AS cat_slug FROM ' . DB::table('article') . ' AS art JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1) WHERE art.id=' . $id . ' AND ' . Admin::articleAccessSql('art'));

    if ($query !== false) {
        $view_count = $query['view_count'];

        if ($returnid == 'load') {
            $returnid = $query['home1'];
        }

        $backlink = Router::admin('content-articles-list', ['query' => ['cat' => $returnid, 'page' => $returnpage]]);
        $actionplus = ['query' => ['id' => $id, 'returnid' => $returnid, 'returnpage' => $returnpage]];
        $submittext = 'global.savechanges';
        $new = false;
        $continue = true;
    }
} else {
    $backlink = Router::admin('content-articles');
    $actionplus = null;
    $submittext = 'global.create';
    $new = true;
    $id = -1;
    $view_count = 0;
    $query = [
        'id' => -1,
        'title' => '',
        'slug' => '',
        'description' => '',
        'perex' => '<p></p>',
        'picture_uid' => null,
        'content' => '',
        'search_content' => '',
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
        'view_count' => 0,
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
    $newdata['slug'] = StringHelper::slugify($slug);
    $newdata['description'] = Html::cut(_e(trim(Request::post('description', ''))), 255);
    $newdata['home1'] = (int) Request::post('home1');
    $newdata['home2'] = (int) Request::post('home2');
    $newdata['home3'] = (int) Request::post('home3');

    if (User::hasPrivilege('adminchangeartauthor')) {
        $newdata['author'] = (int) Request::post('author');
    } else {
        $newdata['author'] = $query['author'];
    }

    $newdata['perex'] = User::filterContent(Html::cut(Request::post('perex', ''), DB::MAX_TEXT_LENGTH), true, false);
    $newdata['content'] = User::filterContent(Html::cut(Request::post('content', ''), DB::MAX_MEDIUMTEXT_LENGTH));
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
            if (DB::count('page', 'type=' . Page::CATEGORY . ' AND id=' . DB::val($newdata[$homecheck]) . ' AND level<=' . User::getLevel()) === 0) {
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
        $search_content = new FulltextContentBuilder();
        $search_content->add($newdata['perex'], ['strip_tags' => true, 'unescape_html' => true]);
        $search_content->add($newdata['content'], ['strip_tags' => true, 'unescape_html' => true, 'remove_hcm' => true]);

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
            'search_content' => $search_content->build(),
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
                'view_count' => 0,
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

            // reset view counter
            if ($newdata['resetread'] == 1) {
                DB::update('article', 'id=' . $id, ['view_count' => 0]);
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
        $message = Message::ok(_lang('global.saved') . ' <small>(' . GenericTemplates::renderTime(time(), 'article_admin') . ')</small>', true);
    }

    if (isset($_GET['created'])) {
        $message = Message::ok(_lang('global.created'));
    }

    // calculate rating
    if (!$new) {
        if ($query['ratenum'] != 0) {
            $rate = _num(DB::result(DB::query('SELECT ROUND(ratesum/ratenum) FROM ' . DB::table('article') . ' WHERE id=' . $query['id']))) . '%'
                . ', ' . _num($query['ratenum']) . 'x';
        } else {
            $rate = _lang('article.rate.nodata');
        }
    } else {
        $rate = '';
    }

    // image
    $picture = '';

    if (isset($query['picture_uid'])) {
        $picture .= '<img src="' . _e(Router::file(Article::getImagePath($query['picture_uid']))) . '" alt="article picture" id="article-edit-picture-file">
<label id="article-edit-picture-delete">' . Form::input('checkbox', 'picture-delete', '1') . ' ' . _lang('global.delete') . '</label>';
    } else {
        $picture .= '<img src="' . _e(Router::path('admin/public/images/art-no-pic.png')) . "\" alt=\"no picture\" id=\"article-edit-picture-file\">\n";
    }

    $picture .= Form::input('file', 'picture', null, ['id' => 'article-edit-picture-upload']) . "\n";

    // save row
    $save_row = Form::input('submit', null, _lang($submittext), ['class' => 'button bigger', 'accesskey' => 's'])
        . (!$new ? '
            <a class="button bigger" href="' . _e(Router::article($query['id'], $query['slug'], $query['cat_slug'])) . '" target="_blank">'
                . '<img src="' . _e(Router::path('admin/public/images/icons/show.png')) . '" alt="show" class="icon">'
                . _lang('global.open')
            . '</a>
    
            <a class="button bigger" href="' . _e(Router::admin('content-articles-delete', ['query' => ['id' => $query['id'], 'returnid' => $query['home1'], 'returnpage' => 1]])) . '">
                <img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">' . StringHelper::ucfirst(_lang('global.delete')) . '
            </a>

            <span class="customsettings">
                <small>' . _lang('admin.content.form.thisid') . ' ' . $query['id'] . '</small>
            </span>
        ' : '');

    // form
    $output .= Admin::backlink($backlink) . '
<h1>' . _lang('admin.content.articles.edit.title') . '</h1>
' . $message . '

' . (($new && !User::hasPrivilege('adminautoconfirm')) ? Admin::note(_lang('admin.content.articles.edit.newconfnote')) : '') . '
' . ((!$new && $query['confirmed'] != 1) ? Admin::note(_lang('admin.content.articles.edit.confnote')) : '') . '

' . ((!$new && DB::count('article', 'id!=' . DB::val($query['id']) . ' AND home1=' . DB::val($query['home1']) . ' AND slug=' . DB::val($query['slug'])) !== 0) ? Message::warning(_lang('admin.content.form.slug.collision')) : '') . '

' . Form::start('artform', [
    'class' => 'cform',
    'action' => Router::admin('content-articles-edit', $actionplus),
    'enctype' => 'multipart/form-data',
]) . '
    <table class="formtable edittable">
        <tbody>
            <tr class="valign-top">
                <td class="form-box main-box">
                    <table>
                        <tbody>
                            <tr>
                                <th>' . _lang('article.category') . '</th>
                                <td>'
                                    . Admin::pageSelect('home1', ['type' => Page::CATEGORY, 'selected' => $query['home1']])
                                    . ' '
                                    . Admin::pageSelect('home2', ['type' => Page::CATEGORY, 'selected' => $query['home2'], 'empty_item' => _lang('admin.content.form.category.none')])
                                    . ' '
                                    . Admin::pageSelect('home3', ['type' => Page::CATEGORY, 'selected' => $query['home3'], 'empty_item' => _lang('admin.content.form.category.none')])
                                    . '
                                </td>
                            </tr>
                            <tr>
                                <th>' . _lang('admin.content.form.title') . '</th>
                                <td>' . Form::input('text', 'title', $query['title'], ['class' => 'inputmax'], false) . '</td>
                            </tr>
                            <tr>
                                <th>' . _lang('admin.content.form.slug') . '</th>
                                <td>' . Form::input('text', 'slug', $query['slug'], ['class' => 'inputmax', 'maxlength' => 255]) . '</td>
                            </tr>
                            <tr>
                                <th>' . _lang('admin.content.form.description') . '</th>
                                <td>' . Form::input('text', 'description', $query['description'], ['class' => 'inputmax', 'maxlength' => 255], false) . '</td>
                            </tr>
                            <tr class="valign-top">
                                <th>' . _lang('admin.content.form.perex') . '</th>
                                <td>' . Admin::editor('article-perex', 'perex', $query['perex'], ['mode' => 'lite', 'rows' => 9, 'class' => 'areabigperex']) . '</td>
                            </tr>
                            <tr class="valign-top">
                                <th>' . _lang('admin.content.form.content') . '</th>
                                <td>' . Admin::editor('article-content', 'content', $query['content']) . '</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            ' . Extend::buffer('admin.article.form', ['article' => $query]) . '
                            <tr class="desktop-only">
                                <td></td>
                                <td>' . $save_row . '</td>
                            </tr>
                       </tfoot>     
                    </table>    
                </td> 
                <td class="form-box">
                    <div id="settingseditform">
                        ' . Extend::buffer('admin.article.settings.before', ['article' => $query]) . '
                        <fieldset>
                            <legend>' . _lang('admin.content.form.picture') . '</legend>
                            <div id="article-edit-picture">' . $picture . '</div>
                        </fieldset>'

                        . (User::hasPrivilege('adminchangeartauthor')
                            ? '<fieldset>
                                <legend>' . _lang('article.author') . '</legend>'
                                . Admin::userSelect('author', ['selected' => $query['author'], 'group_cond' => 'adminart=1', 'class' => 'inputmax'])
                                . '</fieldset>'
                            : '')

                        . '<fieldset id="article-edit-time">
                            <legend>' . _lang('article.posted') . '</legend>
                            ' . Form::editTime('time', $query['time'], ['input_class' => 'inputmax', 'now_toggle' => true, 'now_toggle_default' => $new]) . '
                        </fieldset>
                        
                        <fieldset>
                            <legend>' . _lang('admin.content.form.settings') . '</legend>
                            <table>
                                <tbody>
                                    <tr><td><label>' . Form::input('checkbox', 'public', '1', ['checked' => (bool) $query['public']]) . ' ' . _lang('admin.content.form.public') . '</label></td></tr>
                                    <tr><td><label>' . Form::input('checkbox', 'visible', '1', ['checked' => (bool) $query['visible']]) . ' ' . _lang('admin.content.form.visible') . '</label></td></tr>
                                    ' . ((User::hasPrivilege('adminconfirm') || (User::hasPrivilege('adminautoconfirm') && User::equals($query['author'])))
                                        ? '<tr><td><label>' . Form::input('checkbox', 'confirmed', '1', ['checked' => (bool) $query['confirmed']]) . ' ' . _lang('admin.content.form.confirmed') . '</label></td></tr>'
                                        : '') . '
                                    <tr><td><label>' . Form::input('checkbox', 'comments', '1', ['checked' => (bool) $query['comments']]) . ' ' . _lang('admin.content.form.comments') . '</label></td></tr>
                                    <tr><td><label>' . Form::input('checkbox', 'commentslocked', '1', ['checked' => (bool) $query['commentslocked']]) . ' ' . _lang('admin.content.form.commentslocked') . '</label></td></tr>
                                    <tr><td><label>' . Form::input('checkbox', 'rateon', '1', ['checked' => (bool) $query['rateon']]) . ' ' . _lang('admin.content.form.artrate') . '</label></td></tr>
                                    <tr><td><label>' . Form::input('checkbox', 'showinfo', '1', ['checked' => (bool) $query['showinfo']]) . ' ' . _lang('admin.content.form.showinfo') . '</label></td></tr>
                                    ' . (!$new ? '<tr><td><label>' . Form::input('checkbox', 'resetrate', '1') . ' ' . _lang('admin.content.form.resetartrate') . ' <small>(' . $rate . ')</small></label></td></tr>' : '') . '
                                    ' . (!$new ? '<tr><td><label>' . Form::input('checkbox', 'delcomments', '1') . ' ' . _lang('admin.content.form.delcomments') . ' <small>(' . _num(DB::count('post', 'home=' . DB::val($query['id']) . ' AND type=' . Post::ARTICLE_COMMENT)) . ')</small></label></td></tr>' : '') . '
                                    ' . (!$new ? '<tr><td><label>' . Form::input('checkbox', 'resetread', '1') . ' ' . _lang('admin.content.form.resetviews') . ' <small>(' . _num($view_count) . ')</small></label></td></tr>' : '') . '
                                </tbody>
                            </table>
                        </fieldset>
                        ' . Extend::buffer('admin.article.settings.after', ['article' => $query]) . '
                    </div>
                </td>
            </tr>
            <tr class="mobile-only">
                <td>' . $save_row . '</td>
            </tr>
        </tbody>
    </table>
' . Form::end('artform') . "\n";
} else {
    $output .=
        Admin::backlink(Router::admin('content-articles'))
        . '<h1>' . _lang('admin.content.articles.edit.title') . "</h1>\n"
        . Message::error(_lang('global.badinput'));
}
