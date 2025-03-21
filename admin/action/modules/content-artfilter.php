<?php

use Sunlight\Admin\Admin;
use Sunlight\Article;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\IpLog;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';
$infopage = false;

// process action
if (isset($_POST['category'])) {
    $category = (int) Request::post('category');
    $author = (int) Request::post('author');
    $time = Form::loadTime('time', time());
    $time_op = Request::post('time_op');
    $public = (int) Request::post('public');
    $visible = (int) Request::post('visible');
    $confirmed = (int) Request::post('confirmed');
    $comments = (int) Request::post('comments');
    $rateon = (int) Request::post('rateon');
    $showinfo = (int) Request::post('showinfo');
    $new_category = (int) Request::post('new_category');
    $new_author = (int) Request::post('new_author');
    $new_public = (int) Request::post('new_public');
    $new_visible = (int) Request::post('new_visible');
    $new_confirmed = User::hasPrivilege('adminconfirm') ? (int) Request::post('new_confirmed') : -1;
    $new_comments = (int) Request::post('new_comments');
    $new_rateon = (int) Request::post('new_rateon');
    $new_showinfo = (int) Request::post('new_showinfo');
    $new_delete = Form::loadCheckbox('new_delete');
    $new_resetrate = Form::loadCheckbox('new_resetrate');
    $new_delcomments = Form::loadCheckbox('new_delcomments');
    $new_resetread = Form::loadCheckbox('new_resetread');

    // check vars
    if (
        $category == -1 && !User::hasPrivilege('adminpages')
        || $category != -1 && DB::count('page', 'id=' . DB::val($category) . ' AND type=' . Page::CATEGORY . ' AND level<=' . User::getLevel()) == 0
    ) {
        throw new \UnexpectedValueException(sprintf('Invalid article filter source category ID %d', $category));
    }

    if ($new_category != -1 && DB::count('page', 'id=' . DB::val($new_category) . ' AND type=' . Page::CATEGORY . ' AND level<=' . User::getLevel()) === 0) {
        throw new \UnexpectedValueException(sprintf('Invalid article filter new category ID %d', $new_category));
    }

    if ($new_author != -1 && DB::count('user', 'id=' . DB::val($new_author)) === 0) {
        $new_author = -1;
    }

    // build WHERE condition
    $cond_parts = [];

    if ($category != -1) {
        $cond_parts[] = Article::createCategoryFilter([$category], 'art');
    }

    if ($author != -1) {
        $cond_parts[] = 'art.author=' . DB::val($author);
    }

    if (in_array($time_op, ['<', '=', '>'], true)) {
        $cond_parts[] = 'art.time' . $time_op . DB::val($time);
    }

    foreach (compact('public', 'visible', 'confirmed', 'comments', 'rateon', 'showinfo') as $column => $value) {
        if ($value === 0 || $value === 1) {
            $cond_parts[] = 'art.' . $column . '=' . DB::val($value);
        }
    }

    $cond = implode(' AND ', $cond_parts ?: ['1']);

    // find articles
    $query = DB::query('SELECT art.id,art.title,art.slug,cat.slug AS cat_slug FROM ' . DB::table('article') . ' AS art JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1) WHERE ' . $cond);
    $found = DB::size($query);

    do {
        if ($found === 0) {
            $message = Message::warning(_lang('admin.content.artfilter.f1.noresult'));
            break;
        }

        if (!Form::loadCheckbox('_process')) {
            $infopage = true;
            break;
        }

        while ($item = DB::row($query)) {
            // delete comments
            if ($new_delcomments || $new_delete) {
                DB::delete('post', 'type=' . Post::ARTICLE_COMMENT . ' AND home=' . $item['id']);
            }

            // delete article
            if ($new_delete) {
                DB::delete('article', 'id=' . $item['id']);
                continue;
            }

            // article changes
            $changeset = [];

            // reset rating
            if ($new_resetrate) {
                $changeset += ['ratenum' => 0, 'ratesum' => 0];
                DB::delete('iplog', 'type=' . IpLog::ARTICLE_RATED . ' AND var=' . $item['id']);
            }

            // reset view counter
            if ($new_resetread) {
                $changeset['view_count'] = 0;
            }

            // change category
            if ($new_category != -1) {
                $changeset += [
                    'home1' => $new_category,
                    'home2' => -1,
                    'home3' => -1
                ];
            }

            // change author
            if ($new_author != -1) {
                $changeset['author'] = $new_author;
            }

            // change settings
            $settings = [
                'public' => $new_public,
                'visible' => $new_visible,
                'comments' => $new_comments,
                'rateon' => $new_rateon,
                'showinfo' => $new_showinfo,
                'confirmed' => $new_confirmed,
            ];

            foreach ($settings as $column => $new_value) {
                if ($new_value === 0 || $new_value === 1) {
                    $changeset[$column] = $new_value;
                }
            }

            // apply changeset
            if (!empty($changeset)) {
                DB::update('article', 'id=' . $item['id'], $changeset);
            }
        }

        $message = Message::ok(_lang('global.done'));
    } while (false);
}

// output
$boolSelect = function ($name, $changing = false) {
    return Form::select($name, [
        -1 => $changing ? _lang('global.nochange') : _lang('admin.content.artfilter.f1.bool.doesntmatter'),
        1 => _lang('admin.content.artfilter.f1.bool.mustbe'),
        0 => _lang('admin.content.artfilter.f1.bool.mustntbe'),
    ]);
};

$output .= $message . '
' . Form::start('artfilter');

if (!$infopage) {
    $output .= '
<h2>' . _lang('admin.content.artfilter.f1.title') . '</h2>
<p>' . _lang('admin.content.artfilter.f1.p') . '</p>
<table>

<tr>
<th>' . _lang('article.category') . '</th>
<td>' . Admin::pageSelect('category', ['type' => Page::CATEGORY, 'empty_item' => User::hasPrivilege('adminpages') ? _lang('global.any2') : null]) . '</td>
</tr>

<tr>
<th>' . _lang('article.author') . '</th>
<td>' . Admin::userSelect('author', ['group_cond' => 'adminart=1', 'class' => 'selectmedium', 'extra_option' => _lang('global.any')]) . '</td>
</tr>

<tr>
<th>' . _lang('article.posted') . '</th>
<td>

' . Form::select('time_op', [
    '' => _lang('admin.content.artfilter.f1.time.any'),
    '>' => _lang('admin.content.artfilter.f1.time.gt'),
    '=' => _lang('admin.content.artfilter.f1.time.eq'),
    '<' => _lang('admin.content.artfilter.f1.time.lt'),
]) . '

' . Form::editTime('time', time()) . '

</td>
</tr>

<tr class="valign-top">
<th>' . _lang('admin.content.form.settings') . '</th>
<td>
' . $boolSelect('public') . ' ' . _lang('admin.content.form.public') . '<br>
' . $boolSelect('visible') . ' ' . _lang('admin.content.form.visible') . '<br>
' . $boolSelect('confirmed') . ' ' . _lang('admin.content.form.confirmed') . '<br>
' . $boolSelect('comments') . ' ' . _lang('admin.content.form.comments') . '<br>
' . $boolSelect('rateon') . ' ' . _lang('admin.content.form.artrate') . '<br>
' . $boolSelect('showinfo') . ' ' . _lang('admin.content.form.showinfo') . '
</td>
</tr>

</table>

<br><div class="hr"><hr></div><br>

<h2>' . _lang('admin.content.artfilter.f2.title') . '</h2>
<p>' . _lang('admin.content.artfilter.f2.p') . '</p>
<table>

<tr>
<th>' . _lang('article.category') . '</th>
<td>' . Admin::pageSelect('new_category', ['type' => Page::CATEGORY, 'empty_item' => _lang('global.nochange')]) . '</td>
</tr>

<tr>
<th>' . _lang('article.author') . '</th>
<td>' . Admin::userSelect('new_author', ['group_cond' => 'adminart=1', 'class' => 'selectmedium', 'extra_option' => _lang('global.nochange')]) . '</td>
</tr>

<tr class="valign-top">
<th>' . _lang('admin.content.form.settings') . '</th>
<td>
' . $boolSelect('new_public', true) . ' ' . _lang('admin.content.form.public') . '<br>
' . $boolSelect('new_visible', true) . ' ' . _lang('admin.content.form.visible') . '<br>
' . (User::hasPrivilege('adminconfirm') ? $boolSelect('new_confirmed', true) . ' ' . _lang('admin.content.form.confirmed') . '<br>' : '') . '
' . $boolSelect('new_comments', true) . ' ' . _lang('admin.content.form.comments') . '<br>
' . $boolSelect('new_rateon', true) . ' ' . _lang('admin.content.form.artrate') . '<br>
' . $boolSelect('new_showinfo', true) . ' ' . _lang('admin.content.form.showinfo') . '
</td>
</tr>

<tr class="valign-top">
<th>' . _lang('global.action') . '</th>
<td>
<label>' . Form::input('checkbox', 'new_delete', '1') . ' ' . _lang('global.delete') . '</label><br>
<label>' . Form::input('checkbox', 'new_resetrate', '1') . ' ' . _lang('admin.content.form.resetartrate') . '</label><br>
<label>' . Form::input('checkbox', 'new_delcomments', '1') . ' ' . _lang('admin.content.form.delcomments') . '</label><br>
<label>' . Form::input('checkbox', 'new_resetread', '1') . ' ' . _lang('admin.content.form.resetviews') . '</label>
</td>
</tr>

</table>

<br><div class="hr"><hr></div><br>

' . Form::input('submit', null, _lang('mod.search.submit')) . '
';
} else {
    $output .= Form::renderHiddenInputs(Arr::filterKeys($_POST, null, null, [Xsrf::TOKEN_NAME, '_process'])) . '
' . Form::input('hidden', '_process', '1') . '
' . Message::ok(_lang('admin.content.artfilter.f1.infotext', ['%found%' => _num($found)])) . '
<ul>';

    $counter = 0;

    while ($r = DB::row($query)) {
        if ($counter >= 30) {
            $output .= '<li><em>... (+' . ($found - $counter) . ")</em></li>\n";
            break;
        }

        $output .= '<li><a href="' . _e(Router::article($r['id'], $r['slug'], $r['cat_slug'])) . '" target="_blank">' . $r['title'] . "</a></li>\n";
        ++$counter;
    }

    $output .='</ul>
' . Form::input('submit', null, _lang('global.do2')) . ' <a href="' . _e(Router::admin('content-artfilter')) . '">' . _lang('global.cancel') . '</a>
';
}

$output .= Form::end('artfilter');
