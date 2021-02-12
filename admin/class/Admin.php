<?php

namespace Sunlight\Admin;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\SimpleTreeFilter;
use Sunlight\Extend;
use Sunlight\Page\PageManager;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateService;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\DateTime;
use Sunlight\Util\StringManipulator;
use Sunlight\Xsrf;

abstract class Admin
{
    /**
     * Vykreslit menu
     *
     * @return string
     */
    static function menu(): string
    {
        global $admin_access, $admin_modules, $admin_menu_items, $admin_current_module;

        $output = "<div id=\"menu\">\n";
        if ($admin_access) {
            foreach ($admin_menu_items as $module => $order) {
                if (static::moduleAccess($module)) {
                    $active = (
                        $admin_current_module === $module
                        || !empty($admin_modules[$module]['children']) && in_array($admin_current_module, $admin_modules[$module]['children'])
                    );
                    $url = isset($admin_modules[$module]['url'])
                        ? $admin_modules[$module]['url']
                        : 'index.php?p=' . $module;

                    $output .= '<a href="' . $url . '"'
                        . ($active ? ' class="act"' : '')
                        . '><span>' . $admin_modules[$module]['title'] . "</span></a>\n";
                }
            }
        } else {
            $output .= '<a href="./" class="act"><span>' . _lang('login.title') . "</span></a>\n";
        }
        $output .= "</div>\n";

        return $output;
    }

    /**
     * Vykreslit uziv. menu
     *
     * @return string
     */
    static function userMenu(): string
    {
        $output = '<span id="usermenu">';
        if (_logged_in && _priv_administration) {
            $profile_link = Router::module('profile', 'id=' . _user_name);
            $avatar = User::renderAvatar(Core::$userData, ['get_url' => true, 'default' => false]);
            if ($avatar !== null) {
                $output .= '<a id="usermenu-avatar" href="' . _e($profile_link) . '"><img src="' . $avatar . '" alt="' . _user_name . '"></a>';
            }
            $output .= '<a id="usermenu-username" href="' . _e($profile_link) . '">' . _user_public_name . '</a> [';
            if (_messages) {
                $messages_count = DB::count(_pm_table, '(receiver=' . _user_id . ' AND receiver_deleted=0 AND receiver_readtime<update_time) OR (sender=' . _user_id . ' AND sender_deleted=0 AND sender_readtime<update_time)');
                if ($messages_count != 0) {
                    $messages_count = " <span class='highlight'>(" . $messages_count . ")</span>";
                } else {
                    $messages_count = "";
                }
                $output .= "<a href='" . _e(Router::module('messages')) . "'>" . _lang('usermenu.messages') . $messages_count . "</a>, ";
            }
            $output .= '<a href="' . _e(Router::module('settings')) . '">' . _lang('usermenu.settings') . '</a>, <a href="' . _e(Xsrf::addToUrl(Router::generate('system/script/logout.php?_return=admin/'))) . '">' . _lang('usermenu.logout') . '</a>]';
            $output .= '<a href="' . _e(Core::$url) . '/" target="_blank" class="usermenu-web-link" title="' . _lang('admin.link.site') . '"><img class="icon" src="images/icons/guide.png" alt="' . _lang('admin.link.site') . '"></a>';
        } else {
            $output .= '<a href="./">' . _lang('usermenu.guest') . '</a>';
        }
        $output .= '</span>';

        return $output;
    }

    /**
     * Sestavit kod zpetneho odkazu
     *
     * @param string $url
     * @return string
     */
    static function backlink(string $url): string
    {
        return '<a href="' . _e($url) . '" class="backlink">&lt; ' . _lang('global.return') . "</a>\n";
    }

    /**
     * Sestavit kod poznamky
     *
     * @param string      $str     zprava
     * @param bool        $no_gray nepridavat tridu "note" 1/0
     * @param string|null $icon    nazev ikony nebo null (= 'note')
     * @return string
     */
    static function note(string $str, bool $no_gray = false, ?string $icon = null): string
    {
        return "<p" . ($no_gray ? '' : ' class="note"') . "><img src='images/icons/" . (isset($icon) ? $icon : 'note') . ".png' alt='note' class='icon'>" . $str . "</p>";
    }

    /**
     * Zjistit, zda-li ma uzivatel pristup k modulu
     *
     * @param string $module nazev modulu
     * @return bool
     */
    static function moduleAccess(string $module): bool
    {
        global $admin_modules;

        if (isset($admin_modules[$module])) {
            return (bool) $admin_modules[$module]['access'];
        } else {
            return false;
        }
    }

    /**
     * Sestavit cast sql dotazu pro pristup k ankete - 'where'
     *
     * @param bool   $csep  oddelit SQL dotaz vyrazem ' AND ' zleva 1/0
     * @param string $alias alias tabulky s anketami vcetne tecky
     * @return string
     */
    static function pollAccess(bool $csep = true, string $alias = 'p.'): string
    {
        if ($csep) {
            $csep = " AND ";
        } else {
            $csep = "";
        }

        return ((!_priv_adminallart) ? $csep . "{$alias}author=" . _user_id : $csep . "({$alias}author=" . _user_id . " OR (SELECT level FROM " . _user_group_table . " WHERE id=(SELECT group_id FROM " . _user_table . " WHERE id={$alias}author))<" . _priv_level . ")");
    }

    /**
     * Sestavit cast sql dotazu pro pristup k clanku - 'where'
     *
     * @param string|null $alias alias tabulky clanku nebo null
     * @return string
     */
    static function articleAccess(?string $alias = ''): string
    {
        if ($alias !== '') {
            $alias .= '.';
        }
        if (_priv_adminallart) {
            return " AND (" . $alias . "author=" . _user_id . " OR (SELECT level FROM " . _user_group_table . " WHERE id=(SELECT group_id FROM " . _user_table . " WHERE id=" . (($alias === '') ? "" . _article_table . "." : $alias) . "author))<" . _priv_level . ")";
        } else {
            return " AND " . $alias . "author=" . _user_id;
        }
    }

    /**
     * Sestavit odkaz na clanek ve vypisu
     *
     * @param array $art    data clanku vcetne cat_slug
     * @param bool  $ucnote zobrazovat poznamku o neschvaleni 1/0
     * @return string
     */
    static function articleEditLink(array $art, bool $ucnote = true): string
    {
        $output = "";

        // trida
        $class = "";
        if ($art['visible'] == 0 && $art['public'] == 1) {
            $class = " class='invisible'";
        }
        if ($art['visible'] == 1 && $art['public'] == 0) {
            $class = " class='notpublic'";
        }
        if ($art['visible'] == 0 && $art['public'] == 0) {
            $class = " class='invisible-notpublic'";
        }

        // odkaz
        $output .= "<a href='" . Router::article($art['id'], $art['slug'], $art['cat_slug']) . "' target='_blank'" . $class . ">";
        if ($art['time'] <= time()) {
            $output .= "<strong>";
        }
        $output .= $art['title'];
        if ($art['time'] <= time()) {
            $output .= "</strong>";
        }
        $output .= "</a>";

        // poznamka o neschvaleni
        if ($art['confirmed'] != 1 && $ucnote) {
            $output .= " <small>(" . _lang('global.unconfirmed') . ")</small>";
        }

        return $output;
    }

    /**
     * Sestavit <select> pro vyber stranky
     *
     * Mozne volby v $options:
     * -----------------------
     * selected             ID aktivni polozky (nebo pole, pokud multiple = TRUE)
     * multiple             povolit vyber vice polozek 1/0
     * empty_item           popisek prazdne polozky (ID = -1)
     * type                 omezeni na typ stranky
     * allow_separators     povolit vyber oddelovace 1/0
     * disabled_branches    pole ID stranek, jejichz uzly a podstranky maji byt vynechany z vypisu
     * maxlength            maximalni delka zobrazeneho titulku stranky (null = bez limitu)
     * attrs                HTML retezec s extra atributy pro <select> tag (bez mezery na zacatku)
     *
     * @param string $name    nazev selectu
     * @param array  $options
     * @return string
     */
    static function pageSelect(string $name, array $options): string
    {
        // vychozi volby
        $options += [
            'selected' => -1,
            'multiple' => false,
            'empty_item' => null,
            'type' => null,
            'allow_separators' => false,
            'disabled_branches' => [],
            'maxlength' => 22,
            'attrs' => null,
        ];

        // filtr na typ
        if ($options['type'] !== null) {
            $filter = new SimpleTreeFilter(['type' => $options['type']]);
        } else {
            $filter = null;
        }

        // extend
        Extend::call('admin.page.select', [
            'options' => &$options,
            'filter' => &$filter,
        ]);

        // deaktivovane vetve
        if (!empty($options['disabled_branches'])) {
            $options['disabled_branches'] = array_flip($options['disabled_branches']);
        }

        // nacteni stromu
        $tree = PageManager::getFlatTree(null, null, $filter);

        // vypis
        $output = "<select name='{$name}'"
            . ($options['multiple'] ? ' multiple' : 'ß')
            . ($options['attrs'] !== null ? ' ' . $options['attrs'] : '')
            . ">\n";

        if ($options['empty_item'] !== null) {
            $output .= "<option class='special' value='-1'>{$options['empty_item']}</option>\n";
        }

        $disabledBranchLevel = null;
        foreach ($tree as $page) {
            // filtr deaktivovanych vetvi
            if ($disabledBranchLevel === null) {
                if (isset($options['disabled_branches'][$page['id']])) {
                    $disabledBranchLevel = $page['node_level'];
                }
            } elseif ($page['node_level'] <= $disabledBranchLevel) {
                $disabledBranchLevel = null;
            }

            // vypis stranky
            if ($disabledBranchLevel === null) {
                if ($options['multiple']) {
                    $active = in_array($page['id'], $options['selected']);
                } else {
                    $active = $options['selected'] == $page['id'];
                }

                $output .= "<option value='{$page['id']}'"
                    . ($active ? " selected" : '')
                    . (($options['type'] !== null && $page['type'] != $options['type'] || !$options['allow_separators'] && $page['type'] == _page_separator) ? " disabled" : '')
                    . '>'
                    . str_repeat('&nbsp;&nbsp;&nbsp;│&nbsp;', $page['node_level'])
                    . StringManipulator::ellipsis($page['title'], $options['maxlength'])
                    . "</option>\n";
            }
        }

        if (empty($tree) && $options['empty_item'] === null) {
            $output .= "<option value='-1'>" . _lang('global.nokit') . "</option>\n";
        }

        $output .= "</select>\n";

        return $output;
    }

    /**
     * Sestavit <select> pro vyber uzivatele/skupiny
     *
     * @param string      $name        nazev selectu
     * @param int         $selected    id zvoleneho uzivatele
     * @param string      $gcond       SQL podminka pro zarazeni skupiny
     * @param string|null $class       trida selectu nebo null
     * @param string|null $extraoption popisek extra volby (-1) nebo null (= deaktivovano)
     * @param bool        $groupmode   vybirat pouze cele skupiny 1/0
     * @param int|null    $multiple    povolit vyber vice polozek (size = $multiple) nebo null (= deaktivovano)
     * @return string
     */
    static function userSelect(string $name, int $selected, string $gcond, ?string $class = null, ?string $extraoption = null, bool $groupmode = false, ?int $multiple = null): string
    {
        if ($class != null) {
            $class = " class='" . $class . "'";
        } else {
            $class = "";
        }
        if ($multiple != null) {
            $multiple = " multiple size='" . $multiple . "'";
            $name .= "[]";
        } else {
            $multiple = "";
        }
        $output = "<select name='" . $name . "'" . $class . $multiple . ">";
        $query = DB::query("SELECT id,title,level FROM " . _user_group_table . " WHERE " . $gcond . " AND id!=2 ORDER BY level DESC");
        if ($extraoption != null) {
            $output .= "<option value='-1' class='special'>" . $extraoption . "</option>";
        }

        if (!$groupmode) {
            while ($item = DB::row($query)) {
                $users = DB::query("SELECT id,username,publicname FROM " . _user_table . " WHERE group_id=" . $item['id'] . " AND (" . $item['level'] . "<" . _priv_level . " OR id=" . _user_id . ") ORDER BY id");
                if (DB::size($users) != 0) {
                    $output .= "<optgroup label='" . $item['title'] . "'>";
                    while ($user = DB::row($users)) {
                        if ($selected == $user['id']) {
                            $sel = " selected";
                        } else {
                            $sel = "";
                        }
                        $output .= "<option value='" . $user['id'] . "'" . $sel . ">" . $user[($user['publicname'] !== null) ? 'publicname' : 'username'] . "</option>\n";
                    }
                    $output .= "</optgroup>";
                }
            }
        } else {
            while ($item = DB::row($query)) {
                if ($selected == $item['id']) {
                    $sel = " selected";
                } else {
                    $sel = "";
                }
                $output .= "<option value='" . $item['id'] . "'" . $sel . ">" . $item['title'] . " (" . DB::count(_user_table, 'group_id=' . $item['id']) . ")</option>\n";
            }
        }

        $output .= "</select>";

        return $output;
    }

    /**
     * Sestavit <select> pro vyber layoutu motivu
     *
     * @param string          $name
     * @param string|string[] $selected
     * @param string|null     $empty_option
     * @param int|null        $multiple
     * @param string|null     $class
     * @return string
     */
    static function templateLayoutSelect(string $name, $selected, ?string $empty_option = null, ?int $multiple = null, ?string $class = null): string
    {
        $output = "<select name=\"{$name}\""
            . ($class !== null ? " class=\"{$class}\"" : '')
            . ($multiple !== null ? " multiple size=\"{$multiple}\"" : '')
            . ">\n";

        if ($empty_option !== null) {
            $output .= '<option class="special" value="">' . _e($empty_option) . "</option>\n";
        }

        foreach (Core::$pluginManager->getAllTemplates() as $template) {
            $output .= '<optgroup label="' . _e($template->getOption('name')) . "\">\n";
            foreach ($template->getLayouts() as $layout) {
                $layoutUid = TemplateService::composeUid($template, $layout);
                $layoutLabel = TemplateService::getComponentLabel($template, $layout);

                $active = $multiple === null && $layoutUid === $selected || $multiple !== null && in_array($layoutUid, $selected, true);

                $output .= '<option value="' . _e($layoutUid) . '"' . ($active ? ' selected' : '') . '>'
                    . _e($layoutLabel)
                    . "</option>\n";
            }
            $output .= "</optgroup>\n";
        }

        $output .= "</select>";

        return $output;
    }

    /**
     * Sestavit <select> pro vyber motivu, layoutu a slotu
     *
     * @param string                $name
     * @param string|null           $selected
     * @param string|null           $empty_option
     * @param string|null           $class
     * @param TemplatePlugin[]|null $templates
     * @return string
     */
    static function templateLayoutSlotSelect(string $name, ?string $selected, ?string $empty_option = null, ?string $class = null, ?array $templates = null): string
    {
        $output = "<select name=\"{$name}\""
            . ($class !== null ? " class=\"{$class}\"" : '')
            . ">\n";

        if ($empty_option !== null) {
            $output .= '<option class="special" value="">' . _e($empty_option) . "</option>\n";
        }

        if ($templates === null) {
            $templates = Core::$pluginManager->getAllTemplates();
        }

        foreach ($templates as $template) {
            $output .= '<optgroup label="' . _e($template->getOption('name')) . "\">\n";
            foreach ($template->getLayouts() as $layout) {
                foreach ($template->getSlots($layout) as $slot) {
                    $slotUid = TemplateService::composeUid($template, $layout, $slot);
                    $slotLabel = TemplateService::getComponentLabel($template, $layout, $slot, false);

                    $output .= '<option value="' . _e($slotUid) . '"' . ($selected === $slotUid ? ' selected' : '') . '>'
                        . _e($slotLabel)
                        . "</option>\n";
                }
            }
            $output .= "</optgroup>\n";
        }

        return $output;
    }

    /**
     * Formatovat barvu jako #XXXXXX
     *
     * @param string $value
     * @param bool   $expand
     * @param string $default
     * @return string
     */
    static function formatHtmlColor(string $value, bool $expand = true, string $default = '#000000'): string
    {
        // pripravit hodnotu
        $value = trim($value);
        if ($value === '') {
            // prazdna hodnota
            return $default;
        }
        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        // vytahnout hex cast
        $hex = substr($value, 1);
        if (!ctype_xdigit($hex)) {
            // neplatne znaky
            return $default;
        }
        $hexLen = strlen($hex);

        // zpracovat
        if ($hexLen === 3 && $expand) {
            // zkracena verze
            $output = '#';
            for ($i = 0; $i < $hexLen; ++$i) {
                $output .= str_repeat($hex[$i], 2);
            }

            return $output;
        } elseif ($hexLen === 6) {
            // plna verze
            return $value;
        } else {
            // neplatny pocet znaku
            return $default;
        }
    }

    /**
     * Smazat obrazky z uloziste galerie
     *
     * @param string $sql_cond SQL podminka pro vyber obrazku
     */
    static function deleteGalleryStorage(string $sql_cond): void
    {
        $result = DB::query("SELECT full,(SELECT COUNT(*) FROM " . _gallery_image_table . " WHERE full=toptable.full) AS counter FROM " . _gallery_image_table . " AS toptable WHERE in_storage=1 AND (" . $sql_cond . ") HAVING counter=1");
        while($r = DB::row($result)) {
            @unlink(_root . $r['full']);
        }
    }

    /**
     * Zjisteni, zda ma byt schema tmave
     *
     * @return bool
     */
    static function themeIsDark(): bool
    {
        if (_adminscheme_mode == 0) {
            // vzdy svetle
            return false;
        } elseif (_adminscheme_mode == 1) {
            // vzdy tmave
            return true;
        } else {
            // podle zapadu a vychodu slunce
            $isday = DateTime::isDayTime();
            if ($isday === false) {
                return true;
            }
            return false;
        }
    }

    /**
     * @param int $scheme
     * @param bool $dark
     * @return array
     */
    static function themeAssets(int $scheme, bool $dark): array
    {
        $wysiwygAvailable = false;

        Extend::call('admin.wysiwyg', ['available' => &$wysiwygAvailable]);

        return [
            'extend_event' => 'admin.head',
            'css' => [
                'admin' => Router::generate('admin/script/style.php?s=' . rawurlencode($scheme) . ($dark ? '&d' : '')),
            ],
            'css_after' => "
<!--[if lte IE 7]><link rel=\"stylesheet\" href=\"css/ie7.css\"><![endif]-->
<!--[if IE 8]><link rel=\"stylesheet\" href=\"css/ie8-9.css\"><![endif]-->
<!--[if IE 9]><link rel=\"stylesheet\" href=\"css/ie8-9.css\"><![endif]-->",
            'js' => [
                'jquery' => Router::generate('system/js/jquery.js'),
                'sunlight' => Router::generate('system/js/sunlight.js'),
                'rangyinputs' => Router::generate('system/js/rangyinputs.js'),
                'scrollwatch' => Router::generate('system/js/scrollwatch.js'),
                'scrollfix' => Router::generate('system/js/scrollfix.js'),
                'jquery_ui' => Router::generate('admin/js/jquery-ui.js'),
                'admin' => Router::generate('admin/js/admin.js'),
            ],
            'js_before' => "\n" . Core::getJavascript([
                    'admin' => [
                        'themeIsDark' => $dark,
                        'wysiwygAvailable' => $wysiwygAvailable,
                    ],
                    'labels' => [
                        'cancel' => _lang('global.cancel'),
                        'fmanMovePrompt' => _lang('admin.fman.selected.move.prompt'),
                        'fmanDeleteConfirm' => _lang('admin.fman.selected.delete.confirm'),
                        'busyOverlayText' => _lang('admin.busy_overlay.text'),
                    ],
                ]),
        ];
    }

}
