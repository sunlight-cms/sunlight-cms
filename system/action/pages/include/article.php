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
use Sunlight\Util\UrlHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// nacteni dat
$_article = Article::find($_index->segment, $_page['id']);
if ($_article === false) {
    $_index->notFound();
    return;
}

// kontrola pristupu
if (!Article::checkAccess($_article, false)) {
    $_index->unauthorized();
    return;
}

// drobecek
$_index->crumbs[] = [
    'title' => $_article['title'],
    'url' => Router::article(null, $_article['slug'], $_page['slug'])
];

// meta
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

//  navigace
if ($_article['visible']) {
    $output .= "<div class='article-navigation'><span>" . _lang('article.category') . ": </span>";
    for ($i = 1; $i <= 3; ++$i) {
        if ($_article["cat{$i}_id"] === null) {
            continue;
        }
        if ($i > 1) {
            $output .= ', ';
        }
        $output .= "<a href='" . _e(Router::page($_article["cat{$i}_id"], $_article["cat{$i}_slug"])) . "'>" . $_article["cat{$i}_title"] . "</a>";
    }
    $output .= "</div>\n";
}

//  titulek
$_index->title = $_article['title'];
$_index->heading = null;

// obrazek
if (isset($_article['picture_uid'])) {
    $thumbnail = Article::getThumbnail($_article['picture_uid']);
} else {
    $thumbnail = null;
}

//  perex
Extend::call('article.perex.before', $extend_args);
$output .= "<div class='article-perex'>" . ($thumbnail !== null ? "<img class='article-perex-image' src='" . _e(Router::file($thumbnail)) . "' alt='" . $_article['title'] . "'>" : '') . $_article['perex'] . "</div>\n";
Extend::call('article.perex.after', $extend_args);

//  obsah
$output .= "<div class='article-content'>\n" . Hcm::parse($_article['content']) . "\n</div>\n";
$output .= "<div class='cleaner'></div>\n";

// informace
$infos = [];

if (User::hasPrivilege('adminart')) {
    $infos['idlink'] = [_lang('global.id'), "<a href='" . _e(Router::admin('content-articles-edit', ['query' => ['id' => $_article['id'], 'returnid' => 'load', 'returnpage' => 1]])) . "'>" . $_article['id'] . " <img src='" . Template::image("icons/edit.png") . "' alt='edit' class='icon'></a>"];
}

if ($_article['showinfo']) {
    $infos['author'] = [_lang('article.author'), Router::userFromQuery($_article['author_query'], $_article)];
    $infos['posted'] = [_lang('article.posted'), GenericTemplates::renderTime($_article['time'], 'article')];
    $infos['readnum'] = [_lang('article.readnum'), $_article['readnum'] . 'x'];
}

if ($_article['rateon'] && Settings::get('ratemode') != 0) {
    if ($_article['ratenum'] != 0) {
        if (Settings::get('ratemode') == 1) {
            // procenta
            $rate = (round($_article['ratesum'] / $_article['ratenum'])) . "%";
        } else {
            // znamka
            $rate = round(-0.04 * ($_article['ratesum'] / $_article['ratenum']) + 5);
        }
        $rate .= " (" . _lang('article.rate.num') . " " . $_article['ratenum'] . "x)";
    } else {
        $rate = _lang('article.rate.nodata');
    }

    $infos['rating'] = [_lang('article.rate'), $rate];
}

// formular hodnoceni
$rateform = null;
if ($_article['rateon'] && Settings::get('ratemode') != 0 && User::hasPrivilege('artrate') && IpLog::check(IpLog::ARTICLE_RATED, $_article['id'])) {
    $rateform = "
<strong>" . _lang('article.rate.title') . ":</strong>
<form action='" . _e(Router::path('system/script/artrate.php')) . "' method='post'>
<input type='hidden' name='id' value='" . $_article['id'] . "'>
";

    if (Settings::get('ratemode') == 1) {
        // procenta
        $rateform .= "<select name='r'>\n";
        for ($x = 0; $x <= 100; $x += 10) {
            if ($x == 50) {
                $selected = " selected";
            } else {
                $selected = "";
            }
            $rateform .= "<option value='" . $x . "'" . $selected . ">" . $x . "%</option>\n";
        }
        $rateform .= "</select> \n<input type='submit' value='" . _lang('article.rate.submit') . "'>";
    } else {
        // znamky
        $rateform .= "<table class='article-rating'>\n";
        for ($i = 0; $i < 2; $i++) {
            $rateform .= "<tr class='r" . $i . "'>\n";
            if ($i == 0) {
                $rateform .= "<td rowspan='2'><img src='" . Template::image("icons/rate-good.png") . "' alt='good' class='icon'></td>\n";
            }
            for ($x = 1; $x < 6; $x++) {
                if ($i == 0) {
                    $rateform .= "<td><input type='radio' name='r' value='" . ((5 - $x) * 25) . "'></td>\n";
                } else {
                    $rateform .= "<td>" . $x . "</td>\n";
                }
            }
            if ($i == 0) {
                $rateform .= "<td rowspan='2'><img src='" . Template::image("icons/rate-bad.png") . "' alt='bad' class='icon'></td>\n";
            }
            $rateform .= "</tr>\n";
        }
        $rateform .= "
<tr><td colspan='7'><input type='submit' value='" . _lang('article.rate.submit') . "'></td></tr>
</table>
";
    }

    $rateform .= Xsrf::getInput() . "</form>\n";
}

// sestaveni kodu
Extend::call('article.infos', ['article' => $_article, 'infos' => &$infos]);

if ($rateform !== null || !empty($infos)) {
    // zacatek tabulky
    $output .= "
<table id='article-info' class='article-footer'>
<tr>
";
    
    // informace
    if (!empty($infos)) {
        $output .= '<td>' . GenericTemplates::renderInfos($infos, 'article-info') . "</td>\n";
    }
    
    // hodnoceni
    if ($rateform !== null) {
        $output .= "<td>{$rateform}</td>\n";
    }
    
    // konec
    $output .= "</tr></table>\n";
}

// komentare
Extend::call('article.comments.before', $extend_args);
if ($_article['comments'] && Settings::get('comments')) {
    $output .= PostService::render(PostService::RENDER_ARTICLE_COMMENTS, $_article['id'], $_article['commentslocked']);
}
Extend::call('article.comments.after', $extend_args);

// zapocteni precteni
if ($_article['confirmed'] && $_article['time'] <= time() && IpLog::check(IpLog::ARTICLE_READ, $_article['id'])) {
    DB::update('article', 'id=' . $_article['id'], ['readnum' => DB::raw('readnum+1')]);
    IpLog::update(IpLog::ARTICLE_READ, $_article['id']);
}
