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
use Sunlight\Util\SelectOption;
use Sunlight\Util\StringHelper;
use Sunlight\Xsrf;

abstract class Admin
{
    /**
     * Render menu
     */
    static function menu(): string
    {
        global $_admin;

        $output = "<nav id=\"menu\">\n";

        if ($_admin->access) {
            foreach ($_admin->menu as $module => $order) {
                if (self::moduleAccess($module)) {
                    $active = (
                        $_admin->currentModule === $module
                        || self::isChildModule($_admin->currentModule, $module)
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

        $output .= "</nav>\n";

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
                    $messages_count = ' <span class="highlight">(' . _num($messages_count) . ')</span>';
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
     * Supported options:
     * ------------------
     * - mode ('default')     'default' / 'code' (non-wysiwyg code editor) / 'lite' (short content editor)
     * - format ('html')      'xml' / 'css' / 'js' / 'json' / 'php' / 'php-raw' / 'html'
     * - cols (94)            number of textarea columns
     * - rows (25)            number of textarea rows
     * - wrap (-)             textarea wrap attribute
     * - class ('areabig')    textarea class attribute
     * - double_encode (1)    encode special characters in $content even if already encoded
     *
     * @param string $context descriptive identifier of where the editor is used (for plugins)
     * @param string $name form element name
     * @param string $content editor's contents
     * @param array{
     *     mode?: string,
     *     format?: string,
     *     cols?: int,
     *     rows?: int,
     *     wrap?: string|null,
     *     class?: string,
     *     double_encode?: bool,
     * } $options see description
     */
    static function editor(string $context, string $name, string $content, array $options = []): string
    {
        $options += [
            'mode' => 'default',
            'format' => 'html',
            'cols' => 94,
            'rows' => 25,
            'wrap' => null,
            'class' => 'areabig',
            'double_encode' => true,
        ];

        $output = Extend::buffer('admin.editor', [
            'context' => $context,
            'name' => $name,
            'content' => &$content,
            'options' => &$options,
        ]);

        if ($output === '') {
            // default implementation
            $output = Form::textarea(
                $name,
                $content,
                [
                    'cols' => (int) $options['cols'],
                    'rows' => (int) $options['rows'],
                    'class' => 'editor ' . $options['class'],
                    'data-editor-context' => $context,
                    'data-editor-mode' => $options['mode'],
                    'data-editor-format' => $options['format'],
                ] + ($options['wrap'] === null ? [] : ['wrap' => $options['wrap']]),
                $options['double_encode']
            );
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
     * Check if the given module is a child of the parent module
     */
    static function isChildModule(string $module, string $parentModule): bool
    {
        global $_admin;

        while (($moduleArray = $_admin->modules[$module] ?? null) !== null) {
            if (!isset($moduleArray['parent'])) {
                break;
            }

            if ($moduleArray['parent'] === $parentModule) {
                return true;
            }

            $module = $moduleArray['parent'];
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
    static function articleEditLink(array $art, bool $showFlags = true): string
    {
        $output = '';

        // link
        $output .= '<a'
            . ' href="' . _e(Router::article($art['id'], $art['slug'], $art['cat_slug'])) . '"'
            . ' target="_blank"'
            . ($art['visible'] == 0 ? ' class="invisible-link"' : '')
            . '>';

        $output .= StringHelper::ellipsis($art['title'], 64);
        $output .= '</a>';

        // note
        if ($showFlags) {
            $notes = [];

            if ($art['confirmed'] != 1) {
                $notes[] = _lang('global.unconfirmed');
            } elseif ($art['time'] > time()) {
                $notes[] = _lang('global.unpublished');
            }

            if ($art['public'] == 0) {
                $notes[] = _lang('global.notpublic');
            }

            if (!empty($notes)) {
                $output .= ' <small>(' . implode(', ', $notes) . ')</small>';
            }
        }


        return $output;
    }

    /**
     * Render <select> for page selection
     *
     * Supported options:
     * ------------------
     * - selected (-1)            ID of active page (or an array if multiple = TRUE)
     * - multiple (0)             allow choice of multiple pages 1/0
     * - empty_item (-)           label of empty item (ID = -1)
     * - type (-)                 limit to a single page type
     * - check_access (1)         check access to each page 1/0
     * - check_privilege (0)      also check privilege to manage each page type 1/0 (requires check_access = 1)
     * - allow_separators (0)     make separators selectable 1/0
     * - disabled_branches ([])   array of page IDs whose branches should be excluded
     * - maxlength (22)           max. length of page title or null (unlimited)
     * - attrs ([])               array of extra <select> attributes
     *
     * @param array{
     *     selected?: int|int[],
     *     multiple?: bool,
     *     empty_item?: string|null,
     *     type?: int|null,
     *     check_access?: bool,
     *     check_privilege?: bool,
     *     allow_separators?: bool,
     *     disabled_branches?: int[],
     *     maxlength?: int|null,
     *     attrs?: array|null,
     * } $options see description
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
            'attrs' => [],
        ];

        // @deprecated - required array, accepts string|null to preserve BC
        if (is_string($options['attrs'])) {
            $options['attrs'] = [$options['attrs'] => true];
        }

        // attrs
        $options['attrs']['multiple'] = $options['multiple'];

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

        // compose list
        $choices = [];

        if ($options['empty_item'] !== null) {
            $choices[] = new SelectOption('-1', $options['empty_item'], ['class' => 'special']);
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
                $enabled = (!$options['check_access'] || self::pageAccess($page, $options['check_privilege']))
                    && ($options['type'] === null || $page['type'] == $options['type'])
                    && ($options['allow_separators'] || $page['type'] != Page::SEPARATOR);

                $choices[] = new SelectOption(
                    $enabled ? $page['id'] : '',
                    str_repeat('&nbsp;&nbsp;&nbsp;│&nbsp;', $page['node_level'])
                    . StringHelper::ellipsis($page['title'], $options['maxlength']),
                    ['disabled' => !$enabled],
                    false
                );
            }
        }

        if (empty($tree) && $options['empty_item'] === null) {
            $choices[] = new SelectOption('-1', _lang('global.nokit'));
        }

        return Form::select($name, $choices, $options['selected'], $options['attrs']);
    }

    /**
     * Render <select> for user or group selection
     *
     * Supported options:
     * ------------------
     * - selected (-)         ID or IDs of selected items
     * - group_cond ('1')     SQL condition for groups
     * - user_cond ('1')      SQL condition for users
     * - class (-)            CSS class on the select element
     * - extra_option (-)     add an extra option with this label (value = -1)
     * - select_groups (0)    select groups instead of users 1/0
     * - multiple (-)         render multi-select with this size
     *
     * @param array{
     *     selected?: int|int[]|null,
     *     group_cond?: string,
     *     user_cond?: string,
     *     class?: string|null,
     *     extra_option?: string|null,
     *     select_groups?: bool,
     *     multiple?: int|null,
     * } $options see description
     */
    static function userSelect(string $name, array $options = []): string
    {
        $options += [
            'selected' => [],
            'group_cond' => '1',
            'user_cond' => '1',
            'class' => null,
            'extra_option' => null,
            'select_groups' => false,
            'multiple' => null,
        ];

        if ($options['selected'] !== null) {
            $missingSelectedMap = array_flip(
                is_array($options['selected'])
                    ? $options['selected']
                    : [$options['selected']]
            );
        } else {
            $missingSelectedMap = [];
        }

        $attrs = [];

        if ($options['class'] !== null) {
            $attrs['class'] = $options['class'];
        }

        if ($options['multiple'] != null) {
            $attrs['multiple'] = true;
            $attrs['size'] = $options['multiple'];
            $name .= '[]';
        }

        // compose list
        $choices = [];

        $groupQuery = DB::query(
            'SELECT id,title,level FROM ' . DB::table('user_group')
            . ' WHERE ' . $options['group_cond'] . ' AND id!=' . User::GUEST_GROUP_ID
            . ' ORDER BY level DESC'
        );

        if ($options['extra_option'] != null) {
            $choices[] = new SelectOption('-1', $options['extra_option'], ['class' => 'special']);
        }

        if (!$options['select_groups']) {
            while ($group = DB::row($groupQuery)) {
                $userQuery = DB::query(
                    'SELECT id,username,publicname FROM ' . DB::table('user')
                    . ' WHERE group_id=' . $group['id'] . ' AND (' . $group['level'] . '<' . User::getLevel() . ' OR id=' . User::getId() . ')'
                    . ' ORDER BY COALESCE(publicname, username)'
                );

                if (DB::size($userQuery) != 0) {
                    while ($user = DB::row($userQuery)) {
                        unset($missingSelectedMap[$user['id']]);
                        $choices[$group['title']][] = new SelectOption($user['id'], $user['publicname'] ?? $user['username'], [], false);
                    }
                }
            }

            if (!empty($missingSelectedMap)) {
                $missingUsersQuery = DB::query('SELECT id, username, publicname FROM ' . DB::table('user') . ' WHERE id IN(' . DB::arr(array_keys($missingSelectedMap)) . ')');

                while ($user = DB::row($missingUsersQuery)) {
                    $choices[_lang('global.other')][] = new SelectOption($user['id'], $user['publicname'] ?? $user['username'], [], false);
                }
            }
        } else {
            while ($group = DB::row($groupQuery)) {
                unset($missingSelectedMap[$group['id']]);
                $choices[] = new SelectOption($group['id'], $group['title'] . ' (' . _num(DB::count('user', $options['user_cond'] . ' AND group_id=' . $group['id'])) . ')');
            }

            if (!empty($missingSelectedMap)) {
                $missingGroupsQuery = DB::query('SELECT id,title FROM ' . DB::table('user_group') . ' WHERE id IN(' . DB::arr(array_keys($missingSelectedMap)) . ')');

                while ($group = DB::row($missingGroupsQuery)) {
                    $choices[] = new SelectOption($group['id'], $group['title'] . ' (' . _num(DB::count('user', $options['user_cond'] . ' AND group_id=' . $group['id'])) . ')');
                }
            }
        }

        return Form::select($name, $choices, $options['selected'], $attrs);
    }

    /**
     * Render <select> for template layout
     *
     * @param string|string[]|null $selected
     */
    static function templateLayoutSelect(string $name, $selected, ?string $empty_option = null, ?int $multiple = null, ?string $class = null): string
    {
        $attrs = [];
        if ($class !== null) {
            $attrs['class'] = $class;
        }

        if ($multiple !== null) {
            $attrs['multiple'] = true;
            $attrs['size'] = $multiple;
        }

        $choices = [];
        if ($empty_option !== null) {
            $choices[] = new SelectOption('', $empty_option, ['class' => 'special']);
        }

        foreach (Core::$pluginManager->getPlugins()->getTemplates() as $template) {
            foreach ($template->getLayouts() as $layout) {
                $layoutUid = TemplateService::composeUid($template, $layout);
                $layoutLabel = TemplateService::getComponentLabel($template, $layout);

                $choices[$template->getOption('name')][] = new SelectOption($layoutUid, $layoutLabel);
            }
        }

        return Form::select($name, $choices, $selected, $attrs);
    }

    /**
     * Render <select> for template, layout and slot selection
     *
     * @param TemplatePlugin[]|null $templates
     */
    static function templateLayoutSlotSelect(string $name, ?string $selected, ?string $empty_option = null, ?string $class = null, ?array $templates = null): string
    {
        $attrs = [];
        if($class !== null) {
            $attrs['class'] = $class;
        }

        $choices = [];
        if ($empty_option !== null) {
            $choices[] = new SelectOption('', $empty_option, ['class' => 'special']);
        }

        if ($templates === null) {
            $templates = Core::$pluginManager->getPlugins()->getTemplates();
        }

        foreach ($templates as $template) {
            foreach ($template->getLayouts() as $layout) {
                foreach ($template->getSlots($layout) as $slot) {
                    $slotUid = TemplateService::composeUid($template, $layout, $slot);
                    $slotLabel = TemplateService::getComponentLabel($template, $layout, $slot, false);

                    $choices[$template->getOption('name')][] = new SelectOption($slotUid, $slotLabel);
                }
            }
        }

        return Form::select($name, $choices, $selected, $attrs);
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
