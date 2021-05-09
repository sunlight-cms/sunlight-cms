<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Util\Arr;
use Sunlight\Util\Html;
use Sunlight\Util\Url;

abstract class Router
{
    /**
     * Sestavit adresu k libovolne ceste
     *
     * Cesta bude relativni k zakladni adrese systemu.
     *
     * @param string $path cesta v URL, muze obsahovat query string a fragment
     * @param bool   $absolute
     * @return string
     */
    static function generate(string $path, bool $absolute = false): string
    {
        $url = ($absolute ? Core::$url : Url::base()->path) . '/' . $path;

        Extend::call('link', [
            'path' => $path,
            'absolute' => $absolute,
            'output' => &$url,
        ]);

        return $url;
    }

    /**
     * Sestavit webovou cestu k existujicimu souboru
     *
     * Soubor musi byt umisten v korenovem adresari systemu nebo v jeho podadresarich.
     *
     * @param string $filePath
     * @param bool   $absolute
     * @return string
     */
    static function file(string $filePath, bool $absolute = false): string
    {
        static $realRootPath = null, $realRootPathLength = null;

        if ($realRootPath === null) {
            $realRootPath = realpath(_root) . DIRECTORY_SEPARATOR;
            $realRootPathLength = strlen($realRootPath);
        }

        if (($queryParamPos = strpos($filePath, '?')) !== false) {
            $filePath = substr($filePath, 0, $queryParamPos);
            $params = substr($filePath, $queryParamPos);
        } else {
            $params = '';
        }

        $realFilePath = realpath($filePath);

        if ($realFilePath !== false && substr($realFilePath, 0, $realRootPathLength) === $realRootPath) {
            $path = str_replace('\\', '/', substr($realFilePath, $realRootPathLength));
        } else {
            return '#';
        }

        return self::generate($path, $absolute) . $params;
    }

    /**
     * Sestavit adresu clanku
     *
     * @param int|null    $id            ID clanku
     * @param string|null $slug          jiz nacteny identifikator clanku nebo null
     * @param string|null $category_slug jiz nacteny identifikator kategorie nebo null
     * @param bool        $absolute      sestavit absolutni adresu 1/0
     * @return string
     */
    static function article(?int $id, ?string $slug = null, ?string $category_slug = null, bool $absolute = false): string
    {
        if ($id !== null) {
            if ($slug === null || $category_slug === null) {
                $slug = DB::queryRow("SELECT art.slug AS art_ts, cat.slug AS cat_ts FROM " . _article_table . " AS art JOIN " . _page_table . " AS cat ON(cat.id=art.home1) WHERE art.id=" . $id);
                if ($slug === false) {
                    $slug = ['---', '---'];
                } else {
                    $slug = [$slug['art_ts'], $slug['cat_ts']];
                }
            } else {
                $slug = [$slug, $category_slug];
            }
        } else {
            $slug = [$slug, $category_slug];
        }

        return self::page(null, $slug[1], $slug[0], $absolute);
    }

    /**
     * Sestavit cestu ke strance
     *
     * @param string $slug     cely identifikator stranky (prazdny pro hlavni stranu)
     * @param bool   $absolute sestavit absolutni adresu 1/0
     * @return string
     */
    static function path(string $slug, bool $absolute = false): string
    {
        if (_pretty_urls) {
            $path = $slug;
        } elseif ($slug !== '') {
            $path = 'index.php?p=' . $slug;
        } else {
            $path = '';
        }

        return self::generate($path, $absolute);
    }

    /**
     * Sestavit adresu stranky existujici v databazi
     *
     * @param int|null    $id       ID stranky
     * @param string|null $slug     jiz nacteny identifikator nebo null
     * @param string|null $segment  segment nebo null
     * @param bool        $absolute sestavit absolutni adresu 1/0
     * @return string
     */
    static function page(?int $id, ?string $slug = null, ?string $segment = null, bool $absolute = false): string
    {
        if ($id !== null && $slug === null) {
            $slug = DB::queryRow("SELECT slug FROM " . _page_table . " WHERE id=" . DB::val($id));
            $slug = ($slug !== false ? $slug['slug'] : '---');
        }

        if ($segment !== null) {
            $slug .= '/' . $segment;
        } elseif ($id == _index_page_id) {
            $slug = '';
        }

        return self::path($slug, $absolute);
    }

    /**
     * Sestavit adresu a titulek komentare
     *
     * @param array $post     data komentare (potreba sloupce z {@see Post::createFilter()}
     * @param bool  $absolute sestavit absolutni adresu 1/0
     * @return array adresa, titulek
     */
    static function post(array $post, bool $absolute = false): array
    {
        switch ($post['type']) {
            case _post_section_comment:
            case _post_book_entry:
                return [
                    self::page($post['home'], $post['page_slug'], null, $absolute),
                    $post['page_title'],
                ];
            case _post_article_comment:
                return [
                    self::article(null, $post['art_slug'], $post['cat_slug'], $absolute),
                    $post['art_title'],
                ];
            case _post_forum_topic:
            case _post_pm:
                if ($post['xhome'] == -1) {
                    $topicId = $post[$post['type'] == _post_pm ? 'home' : 'id'];
                } else {
                    $topicId = $post['xhome'];
                }
                if ($post['type'] == _post_forum_topic) {
                    $url = self::topic($topicId, $post['page_slug'], $absolute);
                } else {
                    $url = self::module('messages', "a=list&read={$topicId}", $absolute);
                }

                return [
                    $url,
                    ($post['xhome'] == -1)
                        ? $post['subject']
                        : $post['xhome_subject']
                ,
                ];
            case _post_plugin:
                $url = '';
                $title = '';

                Extend::call("posts.{$post['flag']}.link", [
                    'post' => $post,
                    'url' => &$url,
                    'title' => &$title,
                    'absolute' => $absolute,
                ]);

                return [$url, $title];
            default:
                return ['', ''];
        }
    }

    /**
     * Sestavit adresu tematu
     *
     * @param int         $topic_id   ID tematu
     * @param string|null $forum_slug jiz nacteny identifikator domovskeho fora nebo null
     * @param bool        $absolute   sestavit absolutni adresu 1/0
     * @return string
     */
    static function topic(int $topic_id, ?string $forum_slug = null, bool $absolute = false): string
    {
        if ($forum_slug === null) {
            $forum_slug = DB::queryRow('SELECT r.slug FROM ' . _page_table . ' r WHERE type=' . _page_forum . ' AND id=(SELECT p.home FROM ' . _comment_table . ' p WHERE p.id=' . DB::val($topic_id) . ')');
            if ($forum_slug !== false) {
                $forum_slug = $forum_slug['slug'];
            } else {
                $forum_slug = '---';
            }
        }

        return self::page(null, $forum_slug, $topic_id, $absolute);
    }

    /**
     * Sestavit adresu modulu
     *
     * @param string      $module   jmeno modulu
     * @param string|null $params   standartni querystring
     * @param bool        $absolute sestavit absolutni adresu 1/0
     * @return string
     */
    static function module(string $module, ?string $params = null, bool $absolute = false): string
    {
        if (_pretty_urls) {
            $path = 'm/' . $module;
        } else {
            $path = 'index.php?m=' . $module;
        }

        if (!empty($params)) {
            $path .= (_pretty_urls ? '?' : '&') . $params;
        }

        return self::generate($path, $absolute);
    }

    /**
     * Sestaveni kodu odkazu na uzivatele
     *
     * Mozne klice v $options
     * ----------------------
     * plain (0)        vratit pouze jmeno uzivatele 1/0
     * link (1)         odkazovat na profil uzivatele 1/0
     * color (1)        obarvit podle skupiny 1/0
     * icon (1)         zobrazit ikonu skupiny 1/0
     * publicname (1)   vykreslit publicname, ma-li jej uzivatel vyplneno 1/0
     * new_window (0)   odkazovat do noveho okna 1/0 (v prostredi administrace je vychozi 1)
     * max_len (-)      maximalni delka vykresleneho jmena
     * class (-)        vlastni CSS trida
     * title (-)        titulek
     *
     * @param array $data    samostatna data uzivatele viz {@see User::createQuery()}
     * @param array $options moznosti vykresleni, viz popis funkce
     * @return string HTML kod
     */
    static function user(array $data, array $options = []): string
    {
        // vychozi nastaveni
        $options += [
            'plain' => false,
            'link' => true,
            'color' => true,
            'icon' => true,
            'publicname' => true,
            'new_window' => _env === Core::ENV_ADMIN,
            'max_len' => null,
            'class' => null,
            'title' => null,
        ];

        // extend
        $extendOutput = Extend::buffer('user.link', array('user' => $data, 'options' => &$options));
        if ($extendOutput !== '') {
            return $extendOutput;
        }
        
        $tag = ($options['link'] ? 'a' : 'span');
        $name = $data[$options['publicname'] && $data['publicname'] !== null ? 'publicname' : 'username'];
        $nameIsTooLong = ($options['max_len'] !== null && mb_strlen($name) > $options['max_len']);

        // pouze jmeno?
        if ($options['plain']) {
            if ($nameIsTooLong) {
                return Html::cut($name, $options['max_len']);
            }

            return $name;
        }

        // titulek
        $title = $options['title'];
        if ($nameIsTooLong) {
            if ($title === null) {
                $title = $name;
            } else {
                $title = "{$name}, {$title}";
            }
        }

        // oteviraci tag
        $out = "<{$tag}"
            . ($options['link'] ? ' href="' . _e(self::module('profile', 'id=' .  $data['username'])) . '"' : '')
            . ($options['link'] && $options['new_window'] ? ' target="_blank"' : '')
            . " class=\"user-link user-link-{$data['id']} user-link-group-{$data['group_id']}" . ($options['class'] !== null ? " {$options['class']}" : '') . "\""
            . ($options['color'] && $data['group_color'] !== '' ? " style=\"color:{$data['group_color']}\"" : '')
            . ($title !== null ? " title=\"{$title}\"" : '')
            . '>';

        // ikona skupiny
        if ($options['icon'] && $data['group_icon'] !== '') {
            $out .= "<img src=\"" . self::generate('images/groupicons/' . $data['group_icon']) . "\" title=\"{$data['group_title']}\" alt=\"{$data['group_title']}\" class=\"icon\">";
        }

        // jmeno uzivatele
        if ($nameIsTooLong) {
            $out .= Html::cut($name, $options['max_len']) . '...';
        } else {
            $out .= $name;
        }

        // uzaviraci tag
        $out .= "</{$tag}>";

        return $out;
    }

    /**
     * Sestaveni kodu odkazu na uzivatele na zaklade dat z funkce {@see User::createQuery()}
     *
     * @param array $userQuery vystup z {@see User::createQuery()}
     * @param array $row       radek z vysledku dotazu
     * @param array $options   nastaveni vykresleni, viz {@see Router::user()}
     * @return string
     */
    static function userFromQuery(array $userQuery, array $row, array $options = []): string
    {
        $userData = Arr::getSubset($row, $userQuery['columns'], strlen($userQuery['prefix']));

        if ($userData['id'] === null) {
            return '?';
        }

        return self::user($userData, $options);
    }
}
