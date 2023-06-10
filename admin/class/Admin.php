<?php

namespace Sunlight\Admin;

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\SimpleTreeFilter;
use Sunlight\Extend;
use Sunlight\Page\Page;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateService;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\StringManipulator;
use Sunlight\Xsrf;

abstract class Admin
{
    /**
     * Render menu
     */
    static function menu(): string
    {
        global $_admin;

        $output = "<div id=\"menu\">\n";

        if ($_admin->access) {
            foreach ($_admin->menu as $module => $order) {
                if (self::moduleAccess($module)) {
                    $active = (
                        $_admin->currentModule === $module
                        || !empty($_admin->modules[$module]['children']) && in_array($_admin->currentModule, $_admin->modules[$module]['children'])
                    );
                    $url = $_admin->modules[$module]['url'] ?? Router::admin($module);

                    $output .= '<a href="' . $url . '"'
                        . ($active ? ' class="act"' : '')
                        . '><span>' . $_admin->modules[$module]['title'] . "</span></a>\n";
                }
            }
        } else {
            $output .= '<a href="' . _e(Router::adminIndex()) . '" class="act"><span>' . _lang('login.title') . "</span></a>\n";
        }

        $output .= "</div>\n";

        return $output;
    }

    /**
     * Render user menu
     */
    static function userMenu(bool $dark): string
    {
        $output = '<span id="usermenu">';

        if (User::isLoggedIn() && User::hasPrivilege('administration')) {
            $profile_link = Router::module('profile', ['query' => ['id' => User::getUsername()]]);
            $avatar = User::renderAvatar(User::$data, ['get_url' => true, 'default' => false, 'default_dark' => $dark]);

            if ($avatar !== null) {
                $output .= '<a id="usermenu-avatar" href="' . _e($profile_link) . '"><img src="' . $avatar . '" alt="' . User::getUsername() . '"></a>';
            }

            $output .= '<a id="usermenu-username" href="' . _e($profile_link) . '">' . User::getDisplayName() . '</a> [';

            if (Settings::get('messages')) {
                $messages_count = DB::count('pm', '(receiver=' . User::getId() . ' AND receiver_deleted=0 AND receiver_readtime<update_time) OR (sender=' . User::getId() . ' AND sender_deleted=0 AND sender_readtime<update_time)');

                if ($messages_count != 0) {
                    $messages_count = ' <span class="highlight">(' . $messages_count . ')</span>';
                } else {
                    $messages_count = '';
                }

                $output .= '<a href="' . _e(Router::module('messages')) . '">' . _lang('usermenu.messages') . $messages_count . '</a>, ';
            }

            $output .= '<a href="' . _e(Router::module('settings')) . '">' . _lang('usermenu.settings') . '</a>,'
                . ' <a href="' . _e(Xsrf::addToUrl(Router::path('system/script/logout.php', ['query' => ['_return' => Router::adminIndex()]]))) . '">' . _lang('usermenu.logout') . '</a>]';
            $output .= '<a href="' . _e(Core::getBaseUrl()->getPath()) . '/" target="_blank" class="usermenu-web-link" title="' . _lang('admin.link.site') . '">'
                . '<img class="icon" src="' . _e(Router::path('admin/public/images/icons/guide.png')) . '" alt="' . _lang('admin.link.site') . '">'
                . '</a>';
        } else {
            $output .= '<a href="' . _e(Router::adminIndex()) . '">' . _lang('usermenu.guest') . '</a>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * Render backlink
     */
    static function backlink(string $url): string
    {
        return '<a href="' . _e($url) . '" class="backlink">&lt; ' . _lang('global.return') . "</a>\n";
    }

    /**
     * Render note
     *
     * @param string $str message
     * @param bool $no_gray don't add the "note" class 1/0
     * @param string|null $icon icon name (null = 'note')
     */
    static function note(string $str, bool $no_gray = false, ?string $icon = null): string
    {
        return '<p' . ($no_gray ? '' : ' class="note"') . '><img src="' . _e(Router::path('admin/public/images/icons/' . ($icon ?? 'note') . '.png')) . '" alt="note" class="icon">' . $str . '</p>';
    }

    /**
     * Render a content editor
     *
     * Supported $options:
     * -------------------------------------
     * mode ('default')     'default' / 'code' (non-wysiwyg code editor) / 'lite' (short content editor)
     * format ('html')      'xml' / 'css' / 'js' / 'json' / 'php' / 'php-raw' / 'html'
     * cols (94)            number of textarea columns
     * rows (25)            number of textarea rows
     * wrap (null)          textarea wrap attribute
     * class ('areabig')    textarea class attribute
     *
     * @param string $context descriptive identifier of where the editor is used (for plugins)
     * @param string $name form element name
     * @param string $htmlContent properly escaped HTML content for the editor
     * @param array $options see description
     */
    static function editor(string $context, string $name, string $htmlContent, array $options = []): string
    {
        $options += [
            'mode' => 'default',
            'format' => 'html',
            'cols' => 94,
            'rows' => 25,
            'wrap' => null,
            'class' => 'areabig',
        ];

        $output = Extend::buffer('admin.editor', [
            'context' => $context,
            'name' => $name,
            'html_content' => &$htmlContent,
            'options' => &$options,
        ]);

        if ($output === '') {
            // default implementation
            $output = '<textarea'
                . ' name="' . _e($name) . '"'
                . ' cols="' . ((int) $options['cols']) . '"'
                . ' rows="' . ((int) $options['rows']) . '"'
                . ($options['wrap'] !== null ? ' wrap="' . _e($options['wrap']) . '"' : '')
                . ' class="editor ' . _e($options['class']) . '"'
                . ' data-editor-context="' . _e($context) . '"'
                . ' data-editor-mode="' . _e($options['mode']) . '"'
                . ' data-editor-format="' . _e($options['format']) . '"'
                . '>'
                . $htmlContent
                . '</textarea>';
        }

        return $output;
    }

    /**
     * Check if the user has access to the given module
     */
    static function moduleAccess(string $module): bool
    {
        global $_admin;

        if (isset($_admin->modules[$module])) {
            return (bool) $_admin->modules[$module]['access'];
        }

        return false;
    }

    /**
     * Check if the user has access to the given page
     *
     * @param array{type: int, level: int} $page page data
     * @param bool $checkPrivilege check privilege to manage the page type 1/0
     */
    static function pageAccess(array $page, bool $checkPrivilege = false): bool
    {
        return
            $page['level'] <= User::getLevel()
            && (!$checkPrivilege || User::hasPrivilege('admin' . Page::TYPES[$page['type']]));
    }

    /**
     * Compose SQL condition for poll access
     *
     * @param bool $and begin condition with ' AND'
     * @param string $alias poll table alias including the dot
     */
    static function pollAccess(bool $and = true, string $alias = 'p.'): string
    {
        if ($and) {
            $and = ' AND ';
        } else {
            $and = '';
        }

        return (!User::hasPrivilege('adminallart')
            ? $and . "{$alias}author=" . User::getId()
            : $and . "({$alias}author=" . User::getId() . ' OR (SELECT level FROM ' . DB::table('user_group') . ' WHERE id=(SELECT group_id FROM ' . DB::table('user') . " WHERE id={$alias}author))<" . User::getLevel() . ')');
    }

    /**
     * Compose SQL condition for article access
     */
    static function articleAccessSql(?string $alias = ''): string
    {
        if ($alias !== '') {
            $alias .= '.';
        }

        if (User::hasPrivilege('adminallart')) {
            return '('
                . $alias . 'author=' . User::getId()
                . ' OR (SELECT level FROM ' . DB::table('user_group') . ' WHERE id=(SELECT group_id FROM ' . DB::table('user') . ' WHERE id=' . (($alias === '') ? DB::table('article') . '.' : $alias) . 'author))<' . User::getLevel()
                . ')';
        }

        return $alias . 'author=' . User::getId();
    }

    /**
     * Render link to an article
     *
     * @param array $art article data, including cat_slug
     */
    static function articleEditLink(array $art, bool $showUnconfirmedNote = true): string
    {
        $output = '';

        // class
        $class = '';

        if ($art['visible'] == 0 && $art['public'] == 1) {
            $class = ' class="invisible"';
        }

        if ($art['visible'] == 1 && $art['public'] == 0) {
            $class = ' class="notpublic"';
        }

        if ($art['visible'] == 0 && $art['public'] == 0) {
            $class = ' class="invisible-notpublic"';
        }

        // link
        $output .= '<a href="' . _e(Router::article($art['id'], $art['slug'], $art['cat_slug'])) . '" target="_blank"' . $class . '>';

        if ($art['time'] <= time()) {
            $output .= '<strong>';
        }

        $output .= $art['title'];

        if ($art['time'] <= time()) {
            $output .= '</strong>';
        }

        $output .= '</a>';

        // confirmation note
        if ($art['confirmed'] != 1 && $showUnconfirmedNote) {
            $output .= ' <small>(' . _lang('global.unconfirmed') . ')</small>';
        }

        return $output;
    }

    /**
     * Render <select> for page selection
     *
     * Supported $options:
     * -------------------
     * selected             ID of active page (or an array if multiple = TRUE)
     * multiple             allow choice of multiple pages 1/0
     * empty_item           label of empty item (ID = -1)
     * type                 limit to a single page type
     * check_access         check access to each page 1/0
     * check_privilege      also check privilege to manage each page type 1/0 (requires check_access = 1)
     * allow_separators     make separators selectable 1/0
     * disabled_branches    array of page IDs whose branches should be excluded
     * maxlength            max. length of page title or null (unlimited)
     * attrs                extra HTML with <select> attributes (without space)
     */
    static function pageSelect(string $name, array $options): string
    {
        $options += [
            'selected' => -1,
            'multiple' => false,
            'empty_item' => null,
            'type' => null,
            'check_access' => true,
            'check_privilege' => false,
            'allow_separators' => false,
            'disabled_branches' => [],
            'maxlength' => 22,
            'attrs' => null,
        ];

        // extend
        Extend::call('admin.page.select', ['options' => &$options]);

        // disabled branches
        if (!empty($options['disabled_branches'])) {
            $options['disabled_branches'] = array_flip($options['disabled_branches']);
        }

        // load tree
        if ($options['check_access']) {
            $filter = new PageFilter($options['type'], $options['check_privilege']);
        } elseif ($options['type'] !== null) {
            $filter = new SimpleTreeFilter(['type' => $options['type']]);
        } else {
            $filter = null;
        }

        $tree = Page::getFlatTree(null, null, $filter);

        // list
        $output = '<select name="' . $name . '"'
            . ($options['multiple'] ? ' multiple' : '')
            . ($options['attrs'] !== null ? ' ' . $options['attrs'] : '')
            . ">\n";

        if ($options['empty_item'] !== null) {
            $output .= '<option class="special" value="-1">' . $options['empty_item'] . "</option>\n";
        }

        $disabledBranchLevel = null;

        foreach ($tree as $page) {
            // filter disabled branches
            if ($disabledBranchLevel === null) {
                if (isset($options['disabled_branches'][$page['id']])) {
                    $disabledBranchLevel = $page['node_level'];
                }
            } elseif ($page['node_level'] <= $disabledBranchLevel) {
                $disabledBranchLevel = null;
            }

            // list pages
            if ($disabledBranchLevel === null) {
                if ($options['multiple']) {
                    $active = in_array($page['id'], $options['selected']);
                } else {
                    $active = $options['selected'] == $page['id'];
                }

                $enabled = (!$options['check_access'] || self::pageAccess($page, $options['check_privilege']))
                    && ($options['type'] === null || $page['type'] == $options['type'])
                    && ($options['allow_separators'] || $page['type'] != Page::SEPARATOR);

                $output .= '<option'
                    . ($enabled ? ' value="' . $page['id'] . '"' : ' disabled')
                    . Form::selectOption($active)
                    . '>'
                    . str_repeat('&nbsp;&nbsp;&nbsp;│&nbsp;', $page['node_level'])
                    . StringManipulator::ellipsis($page['title'], $options['maxlength'])
                    . "</option>\n";
            }
        }

        if (empty($tree) && $options['empty_item'] === null) {
            $output .= '<option value="-1">' . _lang('global.nokit') . "</option>\n";
        }

        $output .= "</select>\n";

        return $output;
    }

    /**
     * Render <select> for user or group selection
     *
     * Supported $options:
     * -------------------
     * selected (-)         ID or IDs of selected items
     * group_cond ('1')     SQL condition for groups
     * user_cond ('1')      SQL condition for users
     * class (-)            CSS class on the select element
     * extra_option (-)     add an extra option with this label (value = -1)
     * select_groups (0)    select groups instead of users 1/0
     * multiple (-)         render multi-select with this size
     */
    static function userSelect(string $name, array $options = []): string
    {
        $options += [
            'selected' => null,
            'group_cond' => '1',
            'user_cond' => '1',
            'class' => null,
            'extra_option' => null,
            'select_groups' => false,
            'multiple' => null,
        ];

        if ($options['selected'] !== null) {
            $selectedMap = array_flip(
                is_array($options['selected'])
                    ? $options['selected']
                    : [$options['selected']]
            );
        } else {
            $selectedMap = [];
        }

        $missingSelectedMap = $selectedMap;

        if ($options['class'] !== null) {
            $class = ' class="' . _e($options['class']) . '"';
        } else {
            $class = '';
        }

        if ($options['multiple'] != null) {
            $multiple = ' multiple size="' . _e($options['multiple']) . '"';
            $name .= '[]';
        } else {
            $multiple = '';
        }

        $output = '<select name="' . $name . '"' . $class . $multiple . '>';
        $groupQuery = DB::query(
            'SELECT id,title,level FROM ' . DB::table('user_group')
            . ' WHERE ' . $options['group_cond'] . ' AND id!=' . User::GUEST_GROUP_ID
            . ' ORDER BY level DESC'
        );

        if ($options['extra_option'] != null) {
            $output .= '<option value="-1" class="special">' . $options['extra_option'] . '</option>';
        }

        if (!$options['select_groups']) {
            while ($group = DB::row($groupQuery)) {
                $userQuery = DB::query(
                    'SELECT id,username,publicname FROM ' . DB::table('user')
                    . ' WHERE group_id=' . $group['id'] . ' AND (' . $group['level'] . '<' . User::getLevel() . ' OR id=' . User::getId() . ')'
                    . ' ORDER BY COALESCE(publicname, username)'
                );

                if (DB::size($userQuery) != 0) {
                    $output .= '<optgroup label="' . $group['title'] . '">' ;

                    while ($user = DB::row($userQuery)) {
                        if (isset($selectedMap[$user['id']])) {
                            $sel = true;
                            unset($missingSelectedMap[$user['id']]);
                        } else {
                            $sel = false;
                        }

                        $output .= '<option value="' . $user['id'] . '"' . Form::selectOption($sel) . '>' . ($user['publicname'] ?? $user['username']) . "</option>\n";
                    }

                    $output .= '</optgroup>';
                }
            }

            if (!empty($missingSelectedMap)) {
                $missingUsersQuery = DB::query('SELECT id, username, publicname FROM ' . DB::table('user'). ' WHERE id IN(' . DB::arr(array_keys($missingSelectedMap)) . ')');
                $output .= '<optgroup label="' . _lang('global.other') . '">' ;

                while ($user = DB::row($missingUsersQuery)) {
                    $output .= '<option value="' . $user['id'] . '" selected>' . ($user['publicname'] ?? $user['username']) . "</option>\n";
                }

                $output .= '</optgroup>';
            }
        } else {
            while ($group = DB::row($groupQuery)) {
                if (isset($selectedMap[$group['id']])) {
                    $sel = true;
                    unset($missingSelectedMap[$group['id']]);
                } else {
                    $sel = false;
                }

                $output .= '<option value="' . $group['id'] . '"' . Form::selectOption($sel) . '>' . $group['title'] . ' (' . DB::count('user', $options['user_cond'] . ' AND group_id=' . $group['id']) . ")</option>\n";
            }

            if (!empty($missingSelectedMap)) {
                $missingGroupsQuery = DB::query('SELECT id,title FROM ' . DB::table('user_group') . ' WHERE id IN(' . DB::arr(array_keys($missingSelectedMap)) . ')');

                while ($group = DB::row($missingGroupsQuery)) {
                    $output .= '<option value="' . $group['id'] . '" selected>' . $group['title'] . ' (' . DB::count('user', $options['user_cond'] . ' AND group_id=' . $group['id']) . ")</option>\n";
                }
            }
        }

        $output .= '</select>';

        return $output;
    }

    /**
     * Render <select> for template layout
     *
     * @param string|string[] $selected
     */
    static function templateLayoutSelect(string $name, $selected, ?string $empty_option = null, ?int $multiple = null, ?string $class = null): string
    {
        $output = '<select name="' . $name . '"'
            . ($class !== null ? ' class="' . $class . '"' : '')
            . ($multiple !== null ? ' multiple size="' . $multiple . '"' : '')
            . ">\n";

        if ($empty_option !== null) {
            $output .= '<option class="special" value="">' . _e($empty_option) . "</option>\n";
        }

        foreach (Core::$pluginManager->getPlugins()->getTemplates() as $template) {
            $output .= '<optgroup label="' . _e($template->getOption('name')) . "\">\n";

            foreach ($template->getLayouts() as $layout) {
                $layoutUid = TemplateService::composeUid($template, $layout);
                $layoutLabel = TemplateService::getComponentLabel($template, $layout);

                $active = $multiple === null && $layoutUid === $selected || $multiple !== null && in_array($layoutUid, $selected, true);

                $output .= '<option value="' . _e($layoutUid) . '"' . Form::selectOption($active) . '>'
                    . _e($layoutLabel)
                    . "</option>\n";
            }

            $output .= "</optgroup>\n";
        }

        $output .= '</select>';

        return $output;
    }

    /**
     * Render <select> for template, layout and slot selection
     *
     * @param TemplatePlugin[]|null $templates
     */
    static function templateLayoutSlotSelect(string $name, ?string $selected, ?string $empty_option = null, ?string $class = null, ?array $templates = null): string
    {
        $output = '<select name="' . $name . '"'
            . ($class !== null ? ' class="' . $class . '"' : '')
            . ">\n";

        if ($empty_option !== null) {
            $output .= '<option class="special" value="">' . _e($empty_option) . "</option>\n";
        }

        if ($templates === null) {
            $templates = Core::$pluginManager->getPlugins()->getTemplates();
        }

        foreach ($templates as $template) {
            $output .= '<optgroup label="' . _e($template->getOption('name')) . "\">\n";

            foreach ($template->getLayouts() as $layout) {
                foreach ($template->getSlots($layout) as $slot) {
                    $slotUid = TemplateService::composeUid($template, $layout, $slot);
                    $slotLabel = TemplateService::getComponentLabel($template, $layout, $slot, false);

                    $output .= '<option value="' . _e($slotUid) . '"' . Form::selectOption($selected === $slotUid) . '>'
                        . _e($slotLabel)
                        . "</option>\n";
                }
            }

            $output .= "</optgroup>\n";
        }

        $output .= "</select>\n";

        return $output;
    }

    /**
     * Validate and format HTML color
     */
    static function formatHtmlColor(string $value, bool $expand = true, string $default = '#000000'): string
    {
        // prepare value
        $value = trim($value);

        if ($value === '') {
            // empty value
            return $default;
        }

        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        // extract hex part
        $hex = substr($value, 1);

        if (!ctype_xdigit($hex)) {
            // invalid characters
            return $default;
        }

        $hexLen = strlen($hex);

        // process
        if ($hexLen === 3) {
            // short version
            if ($expand) {
                $output = '#';

                for ($i = 0; $i < $hexLen; ++$i) {
                    $output .= str_repeat($hex[$i], 2);
                }

                return $output;
            }

            return $value;
        }

        if ($hexLen === 6) {
            // full version
            return $value;
        }

        // invalid character count
        return $default;
    }

    /**
     * Remove images from gallery storage
     *
     * @param string $sql_cond SQL condition to find images
     */
    static function deleteGalleryStorage(string $sql_cond): void
    {
        $result = DB::query('SELECT full,(SELECT COUNT(*) FROM ' . DB::table('gallery_image') . ' WHERE full=toptable.full) AS counter FROM ' . DB::table('gallery_image') . ' AS toptable WHERE in_storage=1 AND (' . $sql_cond . ') HAVING counter=1');

        while ($r = DB::row($result)) {
            @unlink(SL_ROOT . $r['full']);
        }
    }

    static function loginAssets(): array
    {
        return [
            'extend_event' => 'admin.head',
            'css' => [
                'admin' => Router::path('admin/script/style.php', ['query' => ['s' => 0]]),
            ],
            'js' => [
                'jquery' => Router::path('system/public/jquery.js'),
                'sunlight' => Router::path('system/public/sunlight.js'),
            ],
            'js_before' => "\n" . Core::getJavascript(),
            'favicon' => (bool) Settings::get('favicon'),
        ];
    }

    static function assets(AdminState $admin): array
    {
        $styleQuery = ['s' => Settings::get('adminscheme')];

        if ($admin->dark) {
            $styleQuery['d'] = 1;
        }

        return [
            'extend_event' => 'admin.head',
            'css' => [
                'admin' => Router::path('admin/script/style.php', ['query' => $styleQuery]),
            ],
            'js' => [
                'jquery' => Router::path('system/public/jquery.js'),
                'sunlight' => Router::path('system/public/sunlight.js'),
                'rangyinputs' => Router::path('system/public/rangyinputs.js'),
                'scrollwatch' => Router::path('system/public/scrollwatch.js'),
                'scrollfix' => Router::path('system/public/scrollfix.js'),
                'jquery_ui' => Router::path('admin/public/jquery-ui.js'),
                'admin' => Router::path('admin/public/admin.js'),
            ],
            'js_before' => "\n" . Core::getJavascript([
                'admin' => [
                    'themeIsDark' => $admin->dark,
                    'wysiwygAvailable' => $admin->wysiwygAvailable,
                    'wysiwygEnabled' => User::isLoggedIn() && User::$data['wysiwyg'],
                ],
                'labels' => [
                    'cancel' => _lang('global.cancel'),
                    'fmanMovePrompt' => _lang('admin.fman.selected.move.prompt'),
                    'fmanDeleteConfirm' => _lang('admin.fman.selected.delete.confirm'),
                    'busyOverlayText' => _lang('admin.busy_overlay.text'),
                ],
            ]),
            'favicon' => (bool) Settings::get('favicon'),
        ];
    }
}
