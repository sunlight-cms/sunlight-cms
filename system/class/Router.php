<?php

namespace Sunlight;

use Kuria\Url\Url;
use Sunlight\Database\Database as DB;
use Sunlight\Page\Page;
use Sunlight\Util\Arr;
use Sunlight\Util\Html;

/**
 * Supported options:
 * ------------------
 * - absolute (false)     generate an absolute URL 1/0
 * - query (null)         array of query parameters
 * - fragment (null)      fragment string (without #)
 *
 * @psalm-type RouterOptions = array{
 *     absolute?: bool|null,
 *     query?: array|null,
 *     fragment?: string|null,
 * }|null
 */
abstract class Router
{
    /**
     * Generate URL for a path
     *
     * @param RouterOptions $options
     */
    static function path(string $path, ?array $options = null): string
    {
        return self::generateUrl(self::createUrl($path), $options);
    }

    /**
     * Generate URL for an existing file
     *
     * - relative paths are resolved automatically
     * - files outside SL_ROOT are not supported
     * - the file path can contain a query string, which will be preserved
     *
     * @param RouterOptions $options
     */
    static function file(string $filePath, ?array $options = null): string
    {
        static $realRootPath = null, $realRootPathLength = null;

        if ($realRootPath === null) {
            $realRootPath = realpath(SL_ROOT) . DIRECTORY_SEPARATOR;
            $realRootPathLength = strlen($realRootPath);
        }

        $queryStringPos = strpos($filePath, '?');

        if ($queryStringPos !== false) {
            parse_str(substr($filePath, $queryStringPos + 1), $query);
            $options = self::combineOptions($options, ['query' => $query]);
            $filePath = substr($filePath, 0, $queryStringPos);
        }

        $realFilePath = realpath($filePath);

        if ($realFilePath !== false && substr($realFilePath, 0, $realRootPathLength) === $realRootPath) {
            $path = str_replace('\\', '/', substr($realFilePath, $realRootPathLength));
        } else {
            return '#';
        }

        return self::path($path, $options);
    }

    /**
     * Generate URL using a slug (for pages, articles, etc.)
     *
     * Index page uses an empty slug.
     *
     * @param RouterOptions $options
     */
    static function slug(string $slug, ?array $options = null): string
    {
        if (Settings::get('pretty_urls')) {
            $url = self::createUrl($slug);
        } elseif ($slug !== '') {
            $url = self::createUrl('index.php/' . $slug);
        } else {
            $url = self::createUrl('');
        }

        return self::generateUrl($url, $options);
    }

    /**
     * Generate URL for a page
     *
     * @param RouterOptions $options
     */
    static function page(?int $id, ?string $slug = null, ?string $segment = null, ?array $options = null): string
    {
        if ($id !== null && $slug === null) {
            $slug = DB::queryRow('SELECT slug FROM ' . DB::table('page') . ' WHERE id=' . DB::val($id));
            $slug = ($slug !== false ? $slug['slug'] : '---');
        }

        if ($segment !== null) {
            $slug .= '/' . $segment;
        } elseif ($id == Settings::get('index_page_id')) {
            $slug = '';
        }

        return self::slug($slug, $options);
    }

    /**
     * Generate URL for the index page
     *
     * @param RouterOptions $options
     */
    static function index(?array $options = null): string
    {
        return self::slug('', $options);
    }

    /**
     * Generate URL to an article
     *
     * @param RouterOptions $options
     */
    static function article(?int $id, ?string $slug = null, ?string $categorySlug = null, ?array $options = null): string
    {
        if ($id !== null) {
            if ($slug === null || $categorySlug === null) {
                $slugs = DB::queryRow('SELECT art.slug AS art_ts, cat.slug AS cat_ts FROM ' . DB::table('article') . ' AS art JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1) WHERE art.id=' . $id);

                if ($slugs !== false) {
                    $slug = $slugs['art_ts'];
                    $categorySlug = $slugs['cat_ts'];
                }
            }
        }

        return self::page(null, $categorySlug ?? '---', $slug ?? '---', $options);
    }

    /**
     * Get a permanent URL for a post
     *
     * @param RouterOptions $options
     */
    static function postPermalink(int $id, ?array $options = null): string
    {
        return self::module('viewpost', self::combineOptions(['query' => ['id' => $id]], $options));
    }

    /**
     * Generate URL to a forum topic
     *
     * @param RouterOptions $options
     */
    static function topic(int $topicId, ?string $forumSlug = null, ?array $options = null): string
    {
        if ($forumSlug === null) {
            $forumSlug = DB::queryRow('SELECT r.slug FROM ' . DB::table('page') . ' r WHERE type=' . Page::FORUM . ' AND id=(SELECT p.home FROM ' . DB::table('post') . ' p WHERE p.id=' . DB::val($topicId) . ')');

            if ($forumSlug !== false) {
                $forumSlug = $forumSlug['slug'];
            } else {
                $forumSlug = '---';
            }
        }

        return self::page(null, $forumSlug, $topicId, $options);
    }

    /**
     * Generate URL to a module
     *
     * @param RouterOptions $options
     */
    static function module(string $module, ?array $options = null): string
    {
        return self::slug('m/' . $module, $options);
    }

    /**
     * Generate URL to an admin module
     *
     * @param RouterOptions $options
     */
    static function admin(?string $module, ?array $options = null): string
    {
        $url = self::createUrl('admin/index.php');
        $url->set('p', $module);

        return self::generateUrl($url, $options);
    }

    /**
     * Generate URL to the admin index
     *
     * @param RouterOptions $options
     */
    static function adminIndex(?array $options = null): string
    {
        return self::generateUrl(self::createUrl('admin/'), $options);
    }

    /**
     * Generate a user profile link
     *
     * Supported options
     * -----------------
     * - plain (0)        return only plain username 1/0
     * - link (1)         link to user profile 1/0
     * - custom_link (-)  custom URL to use instead of user's profile
     * - color (1)        add color based on user group 1/0
     * - icon (1)         show group icon 1/0
     * - publicname (1)   use public name, if available 1/0
     * - new_window (0)   link to a new window 1/0 (defaults to 1 in admin env)
     * - max_len (-)      max. username length
     * - class (-)        custom CSS class
     * - title (-)        title
     * - url (-)          URL options (see class description)
     *
     * @param array $data user data {@see User::createQuery()}
     * @param array{
     *     plain?: bool,
     *     link?: bool,
     *     custom_link?: string|null,
     *     color?: bool,
     *     icon?: bool,
     *     publicname?: bool,
     *     new_window?: bool,
     *     max_len?: int|null,
     *     class?: string|null,
     *     title?: string|null,
     *     url?: RouterOptions,
     * } $options see description
     * @return string HTML code
     */
    static function user(array $data, array $options = []): string
    {
        $options += [
            'plain' => false,
            'link' => true,
            'custom_link' => null,
            'color' => true,
            'icon' => true,
            'publicname' => true,
            'new_window' => Core::$env === Core::ENV_ADMIN,
            'max_len' => null,
            'class' => null,
            'title' => null,
            'url' => null,
        ];

        // extend
        $extendOutput = Extend::buffer('user.link', ['user' => $data, 'options' => &$options]);

        if ($extendOutput !== '') {
            return $extendOutput;
        }
        
        $tag = ($options['link'] ? 'a' : 'span');
        $name = $data[$options['publicname'] && $data['publicname'] !== null ? 'publicname' : 'username'];
        $nameIsTooLong = ($options['max_len'] !== null && mb_strlen($name) > $options['max_len']);

        // plain?
        if ($options['plain']) {
            if ($nameIsTooLong) {
                return Html::cut($name, $options['max_len']);
            }

            return $name;
        }

        // title
        $title = $options['title'];

        if ($nameIsTooLong) {
            if ($title === null) {
                $title = $name;
            } else {
                $title = $name . ', ' . $title;
            }
        }

        // opening tag
        $out = "<{$tag}"
            . ($options['link'] ? ' href="' . _e($options['custom_link'] ?? self::module('profile', self::combineOptions(['query' => ['id' => $data['username']]], $options['url']))) . '"' : '')
            . ($options['link'] && $options['new_window'] ? ' target="_blank"' : '')
            . ' class="user-link user-link-' . $data['id'] . ' user-link-group-' . $data['group_id'] . ($options['class'] !== null ? ' ' . $options['class'] : '') . '"'
            . ($options['color'] && $data['group_color'] !== '' ? ' style="color:' . $data['group_color'] . '"' : '')
            . ($title !== null ? ' title="' . $title . '"' : '')
            . '>';

        // group icon
        if ($options['icon'] && $data['group_icon'] !== '') {
            $out .= '<img src="' . self::path('images/groupicons/' . $data['group_icon']) . '" title="' . $data['group_title'] . '" alt="' . $data['group_title'] . '" class="icon">';
        }

        // username
        if ($nameIsTooLong) {
            $out .= Html::cut($name, $options['max_len']) . '...';
        } else {
            $out .= $name;
        }

        // closing tag
        $out .= "</{$tag}>";

        return $out;
    }

    /**
     * Generate a user profile link using data from {@see User::createQuery()}
     *
     * @param array $userQuery output of {@see User::createQuery()}
     * @param array $row the row to generate a link for
     * @param array{
     *     plain?: bool,
     *     link?: bool,
     *     custom_link?: string,
     *     color?: bool,
     *     icon?: bool,
     *     publicname?: bool,
     *     new_window?: bool,
     *     max_len?: int,
     *     class?: string,
     *     title?: string,
     *     url?: RouterOptions,
     * } $options {@see Router::user()}
     */
    static function userFromQuery(array $userQuery, array $row, array $options = []): string
    {
        $userData = Arr::getSubset($row, $userQuery['columns'], strlen($userQuery['prefix']));

        if ($userData['id'] === null) {
            return '?';
        }

        return self::user($userData, $options);
    }

    private static function createUrl(string $path): Url
    {
        $url = Core::getBaseUrl();
        $url->setPath($url->getPath() . '/' . $path);

        return $url;
    }

    /**
     * @param RouterOptions $options
     */
    private static function generateUrl(Url $url, ?array $options): string
    {
        if (!empty($options['query'])) {
            $url->add($options['query']);
        }

        if (isset($options['fragment'])) {
            $url->setFragment($options['fragment']);
        }

        Extend::call('router.generate', ['url' => $url]);

        return ($options['absolute'] ?? false) ? $url->buildAbsolute() : $url->buildRelative();
    }

    private static function combineOptions(?array $a, ?array $b): ?array
    {
        if (isset($a, $b)) {
            return array_replace_recursive($a, $b);
        }

        return $a ?? $b;
    }
}
