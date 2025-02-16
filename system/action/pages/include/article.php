<?php

use Sunlight\Article;
use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Hcm;
use Sunlight\IpLog;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Form;

defined('SL_ROOT') or exit;

// load article
$_article = Article::find($_index->segment, $_page['id']);

if ($_article === false) {
    $_index->notFound();
    return;
}

// check access
if (!Article::checkAccess($_article, false)) {
    $_index->unauthorized();
    return;
}

// add breadcrumb
$_index->crumbs[] = [
    'title' => $_article['title'],
    'url' => Router::article(null, $_article['slug'], $_page['slug'])
];

// metadata
if ($_article['description'] !== '') {
    $_index->description = $_article['description'];
}

// extend
$continue = true;
$extend_args['article'] = &$_article;

Extend::call('article.before', Extend::args($output, [
    'article' => &$_article,
    'continue' => &$continue,
    'page' => $_page,
]));

if (!$continue) {
    return;
}

// navigation
if ($_article['visible']) {
    $output .= '<div class="article-navigation"><span>' . _lang('article.category') . ': </span>';

    for ($i = 1; $i <= 3; ++$i) {
        if ($_article["cat{$i}_id"] === null) {
            continue;
        }

        if ($i > 1) {
            $output .= ', ';
        }

        $output .= '<a href="' . _e(Router::page($_article["cat{$i}_id"], $_article["cat{$i}_slug"])) . '">' . $_article["cat{$i}_title"] . '</a>';
    }

    $output .= "</div>\n";
}

// title
$_index->title = $_article['title'];
$_index->heading = null;

// image
if (isset($_article['picture_uid'])) {
    $thumbnail = Router::file(Article::getThumbnail($_article['picture_uid']));
} else {
    $thumbnail = Extend::fetch('article.fallback_thumbnail', ['article' => $_article]);
}

// perex
Extend::call('article.perex.before', $extend_args);
$output .= '<div class="article-perex">';

if ($thumbnail !== null) {
    if (isset($_article['picture_uid'])) {
        $output .= '<a href="' . _e(Router::file(Article::getImagePath($_article['picture_uid']))) . '" target="_blank"' . Extend::buffer('image.lightbox', ['group' => 'article']) . '>';
    }

    $output .= '<img class="article-perex-image" src="' . _e($thumbnail) . '" alt="' . $_article['title'] . '">';

    if (isset($_article['picture_uid'])) {
        $output .= '</a>';
    }
}

$output .= $_article['perex']. "</div>\n";
Extend::call('article.perex.after', $extend_args);

// content
$output .= "<div class=\"article-content\">\n" . Hcm::parse($_article['content']) . "\n</div>\n";
$output .= "<div class=\"cleaner\"></div>\n";

// infos
$infos = [];

if ($_article['showinfo']) {
    $infos['author'] = [_lang('article.author'), Router::userFromQuery($_article['author_query'], $_article)];
    $infos['posted'] = [_lang('article.posted'), GenericTemplates::renderDate($_article['time'], 'article')];
    $infos['view_count'] = [_lang('article.view_count'), _num($_article['view_count']) . 'x'];
}

if ($_article['rateon'] && Settings::get('ratemode') != 0) {
    if ($_article['ratenum'] != 0) {
        if (Settings::get('ratemode') == 1) {
            // percentage
            $rate = _num(round($_article['ratesum'] / $_article['ratenum'])) . '%';
        } else {
            // mark
            $rate = _num(round(-0.04 * ($_article['ratesum'] / $_article['ratenum']) + 5));
        }

        $rate .= ' (' . _lang('article.rate.num') . ' ' . _num($_article['ratenum']) . 'x)';
    } else {
        $rate = _lang('article.rate.nodata');
    }

    $infos['rating'] = [_lang('article.rate'), $rate];
}

// rate form
$rateform = null;

if ($_article['rateon'] && Settings::get('ratemode') != 0 && User::hasPrivilege('artrate') && IpLog::check(IpLog::ARTICLE_RATED, $_article['id'])) {
    $rateform = '
<strong>' . _lang('article.rate.title') . ':</strong>
' . Form::start('article-rate', ['action' => Router::path('system/script/artrate.php')]) . '
' . Form::input('hidden', 'id', $_article['id']) . '
';

    if (Settings::get('ratemode') == 1) {
        // percentage
        $rate_choices = [];

        for ($x = 0; $x <= 100; $x += 10) {
            $rate_choices[$x] = $x . '%';
        }

        $rateform .= Form::select('r', $rate_choices, 50);
        $rateform .= "\n" . Form::input('submit', null, _lang('article.rate.submit'));
    } else {
        // marks
        $rateform .= "<table class=\"article-rating\">\n";

        for ($i = 0; $i < 2; $i++) {
            $rateform .= '<tr class="r' . $i. "\">\n";

            if ($i == 0) {
                $rateform .= '<td rowspan="2"><img src="' . _e(Template::asset('images/icons/rate-good.png')) . "\" alt=\"good\" class=\"icon\"></td>\n";
            }

            for ($x = 1; $x < 6; $x++) {
                if ($i == 0) {
                    $rateform .= '<td>' . Form::input('radio', 'r', ((5 - $x) * 25)) . "</td>\n";
                } else {
                    $rateform .= '<td>' . $x . "</td>\n";
                }
            }

            if ($i == 0) {
                $rateform .= '<td rowspan="2"><img src="' . _e(Template::asset('images/icons/rate-bad.png')) . "\" alt=\"bad\" class=\"icon\"></td>\n";
            }

            $rateform .= "</tr>\n";
        }

        $rateform .= '
<tr><td colspan="7">' . Form::input('submit', null, _lang('article.rate.submit')) . '</td></tr>
</table>
';
    }

    $rateform .= Form::end('article-rate') . "\n";
}

// render infos
Extend::call('article.infos', ['article' => $_article, 'infos' => &$infos]);

if ($rateform !== null || !empty($infos)) {
    // table start
    $output .= '
<table id="article-info" class="article-footer">
<tr>
';

    // infos
    if (!empty($infos)) {
        // add admin link only if there already are some infos
        if (User::hasPrivilege('adminart')) {
            $infos['idlink'] = [
                _lang('global.id'),
                '<a href="' . _e(Router::admin('content-articles-edit', ['query' => ['id' => $_article['id'], 'returnid' => 'load', 'returnpage' => 1]])) . '">'
                . $_article['id']
                . ' <img src="' . _e(Template::asset('images/icons/edit.png')) . '" alt="edit" class="icon">'
                . '</a>',
            ];
        }

        // render infos
        $output .= '<td>' . GenericTemplates::renderInfos($infos, 'article-info') . "</td>\n";
    }

    // rating
    if ($rateform !== null) {
        $output .= "<td>{$rateform}</td>\n";
    }

    // table end
    $output .= "</tr></table>\n";
}

// comments
Extend::call('article.comments.before', $extend_args);

if ($_article['comments'] && Settings::get('comments')) {
    $output .= PostService::renderList(PostService::RENDER_ARTICLE_COMMENTS, $_article['id'], $_article['commentslocked']);
}

Extend::call('article.comments.after', $extend_args);

// count view
if (
    $_article['confirmed']
    && $_article['time'] <= time()
    && $_article['author'] != User::getId()
    && IpLog::check(IpLog::ARTICLE_VIEW, $_article['id'])
) {
    DB::update('article', 'id=' . $_article['id'], ['view_count' => DB::raw('view_count+1')]);
    IpLog::update(IpLog::ARTICLE_VIEW, $_article['id']);
}
