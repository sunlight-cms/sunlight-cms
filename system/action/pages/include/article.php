<?php

use Sunlight\Article;
use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Hcm;
use Sunlight\IpLog;
use Sunlight\Picture;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\Xsrf;

defined('_root') or exit;

// nacteni dat
$_article = Article::find($_index['segment'], $_page['id']);
if ($_article === false) {
    $_index['is_found'] = false;
    return;
}

// kontrola pristupu
if (!Article::checkAccess($_article, false)) {
    $_index['is_accessible'] = false;
    return;
}

// drobecek
$_index['crumbs'][] = array(
    'title' => $_article['title'],
    'url' => Router::article(null, $_article['slug'], $_page['slug'])
);

// meta
if ($_article['description'] !== '') {
    $_index['description'] = $_article['description'];
}

// extend
$continue = true;
$extend_args['article'] = &$_article;

Extend::call('article.before', Extend::args($output, array(
    'article' => &$_article,
    'continue' => &$continue,
    'page' => $_page,
)));

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
        $output .= "<a href='" . Router::root($_article["cat{$i}_id"], $_article["cat{$i}_slug"]) . "'>" . $_article["cat{$i}_title"] . "</a>";
    }
    $output .= "</div>\n";
}

//  titulek
$_index['title'] = $_article['title'];
$_index['heading'] = null;

// obrazek
if (isset($_article['picture_uid'])) {
    $thumbnail = Picture::getThumbnail(
        Picture::get(_root . 'images/articles/', null, $_article['picture_uid'], 'jpg'),
        array(
            'mode' => 'fit',
            'x' => _article_pic_thumb_w,
            'y' => _article_pic_thumb_h,
        )
    );
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
$infos = array();

if (_priv_adminart) {
    $infos['idlink'] = array(_lang('global.id'), "<a href='admin/index.php?p=content-articles-edit&amp;id=" . $_article['id'] . "&amp;returnid=load&amp;returnpage=1'>" . $_article['id'] . " <img src='" . Template::image("icons/edit.png") . "' alt='edit' class='icon'></a>");
}

if ($_article['showinfo']) {
    $infos['author'] = array(_lang('article.author'), Router::userFromQuery($_article['author_query'], $_article));
    $infos['posted'] = array(_lang('article.posted'), GenericTemplates::renderTime($_article['time'], 'article'));
    $infos['readnum'] = array(_lang('article.readnum'), $_article['readnum'] . 'x');
}

if ($_article['rateon'] && _ratemode != 0) {
    if ($_article['ratenum'] != 0) {
        if (_ratemode == 1) {
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

    $infos['rating'] = array(_lang('article.rate'), $rate);
}

// formular hodnoceni
$rateform = null;
if ($_article['rateon'] && _ratemode != 0 && _priv_artrate && IpLog::check(_iplog_article_rated, $_article['id'])) {
    $rateform = "
<strong>" . _lang('article.rate.title') . ":</strong>
<form action='" . Router::link('system/script/artrate.php') . "' method='post'>
<input type='hidden' name='id' value='" . $_article['id'] . "'>
";

    if (_ratemode == 1) {
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
Extend::call('article.infos', array('article' => $_article, 'infos' => &$infos));

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
if ($_article['comments'] && _comments) {
    $output .= CommentService::render(CommentService::RENDER_ARTICLE_COMMENTS, $_article['id'], $_article['commentslocked']);
}
Extend::call('article.comments.after', $extend_args);

// zapocteni precteni
if ($_article['confirmed'] && $_article['time'] <= time() && IpLog::check(_iplog_article_read, $_article['id'])) {
    DB::update(_articles_table, 'id=' . $_article['id'], array('readnum' => DB::raw('readnum+1')));
    IpLog::update(_iplog_article_read, $_article['id']);
}
