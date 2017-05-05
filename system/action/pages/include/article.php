<?php

use Sunlight\Comment\CommentService;

if (!defined('_root')) {
    exit;
}

// nacteni dat
$_article = _findArticle($_index['segment'], $_page['id']);
if (false === $_article) {
    $_index['is_found'] = false;
    return;
}

// kontrola pristupu
if (!_articleAccess($_article, false)) {
    $_index['is_accessible'] = false;
    return;
}

// drobecek
$_index['crumbs'][] = array(
    'title' => $_article['title'],
    'url' => _linkArticle(null, $_article['slug'], $_page['slug'])
);

// extend
$continue = true;
$extend_args['article'] = &$_article;

Sunlight\Extend::call('article.before', Sunlight\Extend::args($output, array(
    'article' => &$_article,
    'continue' => &$continue,
    'page' => $_page,
)));

if (!$continue) {
    return;
}

//  navigace
if ($_article['visible']) {
    $output .= "<div class='article-navigation'><span>" . $_lang['article.category'] . ": </span>";
    for ($i = 1; $i <= 3; ++$i) {
        if (null === $_article["cat{$i}_id"]) {
            continue;
        }
        if ($i > 1) {
            $output .= ', ';
        }
        $output .= "<a href='" . _linkRoot($_article["cat{$i}_id"], $_article["cat{$i}_slug"]) . "'>" . $_article["cat{$i}_title"] . "</a>";
    }
    $output .= "</div>\n";
}

//  titulek
$_index['title'] = $_article['title'];
$_index['heading'] = null;

// obrazek
if (isset($_article['picture_uid'])) {
    $thumbnail = _pictureThumb(
        _pictureStorageGet(_root . 'images/articles/', null, $_article['picture_uid'], 'jpg'),
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
Sunlight\Extend::call('article.perex.before', $extend_args);
$output .= "<div class='article-perex'>" . (null !== $thumbnail ? "<img class='article-perex-image' src='" . _e(_linkFile($thumbnail)) . "' alt='" . $_article['title'] . "'>" : '') . $_article['perex'] . "</div>\n";
Sunlight\Extend::call('article.perex.after', $extend_args);

//  obsah
$output .= "<div class='article-content'>\n" . _parseHCM($_article['content']) . "\n</div>\n";
$output .= "<div class='cleaner'></div>\n";

// informace
$infos = array();

if (_priv_adminart) {
    $infos['idlink'] = array($_lang['global.id'], "<a href='admin/index.php?p=content-articles-edit&amp;id=" . $_article['id'] . "&amp;returnid=load&amp;returnpage=1'>" . $_article['id'] . " <img src='" . _templateImage("icons/edit.png") . "' alt='edit' class='icon'></a>");
}

if ($_article['showinfo']) {
    $infos['author'] = array($_lang['article.author'], _linkUserFromQuery($_article['author_query'], $_article));
    $infos['posted'] = array($_lang['article.posted'], _formatTime($_article['time'], 'article'));
    $infos['readnum'] = array($_lang['article.readnum'], $_article['readnum'] . 'x');
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
        $rate .= " (" . $_lang['article.rate.num'] . " " . $_article['ratenum'] . "x)";
    } else {
        $rate = $_lang['article.rate.nodata'];
    }

    $infos['rating'] = array($_lang['article.rate'], $rate);
}

// formular hodnoceni
$rateform = null;
if ($_article['rateon'] && _ratemode != 0 && _priv_artrate && _iplogCheck(_iplog_article_rated, $_article['id'])) {
    $rateform = "
<strong>" . $_lang['article.rate.title'] . ":</strong>
<form action='" . _link('system/script/artrate.php') . "' method='post'>
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
        $rateform .= "</select> \n<input type='submit' value='" . $_lang['article.rate.submit'] . "'>";
    } else {
        // znamky
        $rateform .= "<table class='article-rating'>\n";
        for ($i = 0; $i < 2; $i++) {
            $rateform .= "<tr class='r" . $i . "'>\n";
            if ($i == 0) {
                $rateform .= "<td rowspan='2'><img src='" . _templateImage("icons/rate-good.png") . "' alt='good' class='icon'></td>\n";
            }
            for ($x = 1; $x < 6; $x++) {
                if ($i == 0) {
                    $rateform .= "<td><input type='radio' name='r' value='" . ((5 - $x) * 25) . "'></td>\n";
                } else {
                    $rateform .= "<td>" . $x . "</td>\n";
                }
            }
            if ($i == 0) {
                $rateform .= "<td rowspan='2'><img src='" . _templateImage("icons/rate-bad.png") . "' alt='bad' class='icon'></td>\n";
            }
            $rateform .= "</tr>\n";
        }
        $rateform .= "
<tr><td colspan='7'><input type='submit' value='" . $_lang['article.rate.submit'] . "'></td></tr>
</table>
";
    }

    $rateform .= _xsrfProtect() . "</form>\n";
}

// sestaveni kodu
Sunlight\Extend::call('article.infos', array('article' => $_article, 'infos' => &$infos));

if (null !== $rateform || !empty($infos)) {
    // zacatek tabulky
    $output .= "
<table id='article-info' class='article-footer'>
<tr>
";
    
    // informace
    if (!empty($infos)) {
        $output .= '<td>' . _renderInfos($infos, 'article-info') . "</td>\n";
    }
    
    // hodnoceni
    if (null !== $rateform) {
        $output .= "<td>{$rateform}</td>\n";
    }
    
    // konec
    $output .= "</tr></table>\n";
}

// komentare
Sunlight\Extend::call('article.comments.before', $extend_args);
if ($_article['comments'] && _comments) {
    $output .= CommentService::render(CommentService::RENDER_ARTICLE_COMMENTS, $_article['id'], $_article['commentslocked']);
}
Sunlight\Extend::call('article.comments.after', $extend_args);

// zapocteni precteni
if ($_article['confirmed'] && $_article['time'] <= time() && _iplogCheck(_iplog_article_read, $_article['id'])) {
    DB::query("UPDATE " . _articles_table . " SET readnum=readnum+1 WHERE id=" . $_article['id']);
    _iplogUpdate(_iplog_article_read, $_article['id']);
}
