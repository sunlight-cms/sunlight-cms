<?php

namespace Sunlight;

use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Exception\ContentPrivilegeException;
use Sunlight\Image\ImageException;
use Sunlight\Image\ImageLoader;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageStorage;
use Sunlight\Image\ImageTransformer;
use Sunlight\Util\Arr;
use Sunlight\Util\Cookie;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\StringHelper;
use Sunlight\Util\UrlHelper;

abstract class User
{
    /** Max possible user level */
    const MAX_LEVEL = 10001;
    /** Max assignable user level */
    const MAX_ASSIGNABLE_LEVEL = 9999;
    /** Admin group ID  */
    const ADMIN_GROUP_ID = 1;
    /** Guest group ID (anonymous users) */
    const GUEST_GROUP_ID = 2;
    /** Default registered user group ID */
    const REGISTERED_GROUP_ID = 3;
    /** Cookie name - session */
    const COOKIE_SESSION = 'sl_session';
    /** Cookie name - persistent login key */
    const COOKIE_PERSISTENT_LOGIN = 'sl_persistent_login';
    /** Auth hash type - remember me */
    const AUTH_PERSISTENT_LOGIN = 'persistent_login';
    /** Auth hash type - session */
    const AUTH_SESSION = 'session';
    /** Auth hash type - mass email management */
    const AUTH_MASSEMAIL = 'massemail';
    /** Auth hash type - password reset */
    const AUTH_PASSWORD_RESET = 'password_reset';
    /** Login status - wrong username or password  */
    const LOGIN_FAILURE = 0;
    /** Login status - successful */
    const LOGIN_SUCCESS = 1;
    /** Login status - user or group is blocked  */
    const LOGIN_BLOCKED = 2;
    /** Login status - user account removed */
    const LOGIN_REMOVED = 3;
    /** Login status - attempt limit exceeded */
    const LOGIN_ATTEMPTS_EXCEEDED = 4;
    /** Login status - XSRF failure */
    const LOGIN_XSRF_FAILURE = 5;

    /** @var bool */
    private static $initialized = false;

    /** @var array */
    private static $privilegeMap = [
        'administration' => true,
        'adminsettings' => true,
        'adminplugins' => true,
        'adminusers' => true,
        'admingroups' => true,
        'admincontent' => true,
        'adminother' => true,
        'adminpages' => true,
        'adminsection' => true,
        'admincategory' => true,
        'adminbook' => true,
        'adminseparator' => true,
        'admingallery' => true,
        'adminlink' => true,
        'admingroup' => true,
        'adminforum' => true,
        'adminpluginpage' => true,
        'adminart' => true,
        'adminallart' => true,
        'adminchangeartauthor' => true,
        'adminpoll' => true,
        'adminpollall' => true,
        'adminsbox' => true,
        'adminbox' => true,
        'adminconfirm' => true,
        'adminautoconfirm' => true,
        'fileaccess' => true,
        'fileglobalaccess' => true,
        'fileadminaccess' => true,
        'adminhcmphp' => true,
        'adminbackup' => true,
        'adminmassemail' => true,
        'adminposts' => true,
        'changeusername' => true,
        'unlimitedpostaccess' => true,
        'locktopics' => true,
        'stickytopics' => true,
        'movetopics' => true,
        'postcomments' => true,
        'artrate' => true,
        'pollvote' => true,
        'selfremove' => true,
    ];

    /** @var array|null data from user table (if logged in) */
    static $data;

    /** @var array data from group table */
    static $group;

    static function init(): void
    {
        if (self::$initialized) {
            throw new \LogicException('Already initialized');
        }

        Extend::call('user.privileges', ['privileges' => &self::$privilegeMap]);

        self::authenticate();
        self::$initialized = true;
    }

    static function getPrivilegeMap(): array
    {
        return self::$privilegeMap;
    }

    private static function authenticate(): void
    {
        $success = false;
        $isPersistentLogin = false;
        $errorCode = null;
        $userData = null;

        do {
            $loginDataExist = isset($_SESSION['user_id'], $_SESSION['user_auth']);

            // check persistent login cookie if there are no login data
            if (!$loginDataExist) {
                // check cookie existence
                if (Cookie::exists(self::COOKIE_PERSISTENT_LOGIN)) {
                    // cookie auth process
                    do {
                        // parse cookie
                        $cookie = explode('$', Cookie::get(self::COOKIE_PERSISTENT_LOGIN), 2);

                        if (count($cookie) !== 2) {
                            // invalid cookie format
                            $errorCode = 1;
                            break;
                        }

                        $cookie = [
                            'id' => (int) $cookie[0],
                            'hash' => $cookie[1],
                        ];

                        // fetch user data
                        $userData = DB::queryRow('SELECT * FROM ' . DB::table('user') . ' WHERE id=' . DB::val($cookie['id']));

                        if ($userData === false) {
                            // user not found
                            $errorCode = 2;
                            break;
                        }

                        // check failed login attempt limit
                        if (!IpLog::check(IpLog::FAILED_LOGIN_ATTEMPT)) {
                            // limit exceeded
                            $errorCode = 3;
                            break;
                        }

                        $validHash = self::getAuthHash(self::AUTH_PERSISTENT_LOGIN, $userData['email'], $userData['password']);

                        if ($validHash !== $cookie['hash']) {
                            // invalid hash
                            IpLog::update(IpLog::FAILED_LOGIN_ATTEMPT);
                            $errorCode = 4;
                            break;
                        }

                        // all is well! use cookie data to login the user
                        self::login($cookie['id'], $userData['password'], $userData['email']);
                        $loginDataExist = true;
                        $isPersistentLogin = true;
                    } while (false);

                    // check result
                    if ($errorCode !== null) {
                        // cookie authorization has failed, remove the cookie
                        Cookie::remove(self::COOKIE_PERSISTENT_LOGIN);
                        break;
                    }
                }
            }

            // check whether login data exist
            if (!$loginDataExist) {
                // no login data - user is not logged in
                $errorCode = 5;
                break;
            }

            // fetch user data
            if (!$userData) {
                $userData = DB::queryRow('SELECT * FROM ' . DB::table('user') . ' WHERE id=' . DB::val($_SESSION['user_id']));

                if ($userData === false) {
                    // user not found
                    $errorCode = 6;
                    break;
                }
            }

            // check user authentication hash
            if ($_SESSION['user_auth'] !== self::getAuthHash(self::AUTH_SESSION, $userData['email'], $userData['password'])) {
                // invalid hash
                $errorCode = 7;
                break;
            }

            // check user account's status
            if ($userData['blocked']) {
                // account is blocked
                $errorCode = 8;
                break;
            }

            // fetch group data
            $groupData = DB::queryRow('SELECT * FROM ' . DB::table('user_group') . ' WHERE id=' . DB::val($userData['group_id']));

            if ($groupData === false) {
                // group data not found
                $errorCode = 9;
                break;
            }

            // check group status
            if ($groupData['blocked']) {
                // group is blocked
                $errorCode = 10;
                break;
            }

            // all is well! user is authenticated
            $success = true;
        } while (false);

        // process login
        if ($success) {
            // increase level for superusers
            if ($userData['levelshift']) {
                ++$groupData['level'];
            }

            // record activity time (max once per 30 seconds)
            if (time() - $userData['activitytime'] > 30) {
                DB::update('user', 'id=' . DB::val($userData['id']), [
                    'activitytime' => time(),
                    'ip' => Core::getClientIp(),
                ]);
            }

            // event
            Extend::call('user.auth.success', [
                'user' => &$userData,
                'group' => &$groupData,
                'persistent' => $isPersistentLogin,
            ]);

            // set variables
            self::$data = $userData;
            self::$group = $groupData;
        } else {
            // guest
            $groupData = DB::queryRow('SELECT * FROM ' . DB::table('user_group') . ' WHERE id=' . self::GUEST_GROUP_ID);

            if ($groupData === false) {
                throw new \RuntimeException(sprintf('Anonymous user group was not found (id=%s)', self::GUEST_GROUP_ID));
            }

            // event
            Extend::call('user.auth.failure', ['error_code' => $errorCode]);

            // log
            if ($errorCode !== 5) {
                Logger::debug('user', 'User auth failure', [
                    'error_code' => $errorCode,
                    'user_id' => is_array($userData) ? $userData['id'] : null,
                ]);
            }

            self::$group = $groupData;
        }
    }

    static function isLoggedIn(): bool
    {
        return self::$data !== null;
    }

    static function getId(): int
    {
        return self::$data['id'] ?? -1;
    }

    /**
     * See if user ID matches the current user
     */
    static function equals(int $targetUserId): bool
    {
        return $targetUserId == self::getId();
    }

    /**
     * Get user's privilege level
     */
    static function getLevel(): int
    {
        return self::$group['level'];
    }

    /**
     * See if a privilege has been granted
     */
    static function hasPrivilege(string $name): bool
    {
        return isset(self::$privilegeMap[$name]) && self::$group[$name];
    }
    
    static function isSuperAdmin(): bool
    {
        return self::isLoggedIn() && self::$data['levelshift'] && self::$group['id'] == self::ADMIN_GROUP_ID;
    }

    /**
     * Verify user's password
     */
    static function checkPassword(string $plainPassword): bool
    {
        if (self::isLoggedIn()) {
            return Password::load(self::$data['password'])->match($plainPassword);
        }

        return false;
    }

    /**
     * See if the user can access another's content
     */
    static function checkLevel(int $targetUserId, int $targetUserLevel): bool
    {
        return self::isLoggedIn() && (self::getLevel() > $targetUserLevel || self::equals($targetUserId));
    }

    /**
     * See if the user can access content which may not be public or may require some privilege level
     *
     * @param bool $public content is public 1/0
     * @param int|null $level minimal required level
     */
    static function checkPublicAccess(bool $public, ?int $level = null): bool
    {
        return (self::isLoggedIn() || $public) && ($level === null || self::getLevel() >= $level);
    }

    /**
     * Get user's home directory
     *
     * The directory may not exist!
     *
     * @param bool $getTopmost get topmost possible directory 1/0
     * @throws \RuntimeException if user has no filesystem access
     */
    static function getHomeDir(bool $getTopmost = false): string
    {
        if (!self::hasPrivilege('fileaccess')) {
            throw new \RuntimeException('User has no filesystem access');
        }

        if (self::hasPrivilege('fileglobalaccess')) {
            if ($getTopmost && self::hasPrivilege('fileadminaccess')) {
                $homeDir = SL_ROOT;
            } else {
                $homeDir = SL_ROOT . 'upload/';
            }
        } else {
            $subPath = 'home/' . self::getUsername() . '/';
            Extend::call('user.home_dir', ['subpath' => &$subPath]);
            $homeDir = SL_ROOT . 'upload/' . $subPath;
        }

        return $homeDir;
    }

    /**
     * Normalize a directory path using the user's privileges
     *
     * @return string path with a "/" at the end
     */
    static function normalizeDir(?string $dirPath): string
    {
        if (
            $dirPath !== null
            && $dirPath !== ''
            && ($dirPath = self::checkPath($dirPath, false, true)) !== false
            && is_dir($dirPath)
        ) {
            return $dirPath;
        }

        return self::getHomeDir();
    }

    /**
     * Check if the user can access the given path
     *
     * @param string $path the patch to check
     * @param bool $isFile treat path as a file path
     * @param bool $getPath return the normalized path if successful 1/0
     * @return bool|string
     */
    static function checkPath(string $path, bool $isFile, bool $getPath = false)
    {
        if (self::hasPrivilege('fileaccess')) {
            $path = Filesystem::resolvePath($path, $isFile, self::getHomeDir(true));

            if ($path !== null && (!$isFile || self::checkFilename($path))) {
                return $getPath ? $path : true;
            }
        }

        return false;
    }

    /**
     * Move a file uploaded by the user
     */
    static function moveUploadedFile(string $path, string $newPath): bool
    {
        $handled = false;

        Extend::call('user.move_uploaded_file', [
            'path' => $path,
            'new_path' => $newPath,
            'handled' => &$handled,
        ]);

        return $handled || move_uploaded_file($path, $newPath);
    }

    /**
     * See if the user can access the given file name
     *
     * Only file name is checked, not the path! {@see User::checkPath()}
     */
    static function checkFilename(string $filename): bool
    {
        return
            self::hasPrivilege('fileaccess')
            && (
                self::hasPrivilege('fileadminaccess')
                || Filesystem::isSafeFile($filename)
            );
    }

    /**
     * Filter user's content based on privileges
     *
     * @param string $content the content
     * @param bool $isHtml indicate HTML content 1/0
     * @param bool $hasHcm the content can contain HCM modules 1/0
     * @throws ContentPrivilegeException
     */
    static function filterContent(string $content, bool $isHtml = true, bool $hasHcm = true): string
    {
        if ($hasHcm) {
            if (!$isHtml) {
                throw new \LogicException('Content that supports HCM modules is always HTML');
            }

            $content = Hcm::filter($content, true);
        }

        Extend::call('user.filter_content', [
            'content' => &$content,
            'is_html' => $isHtml,
            'has_hcm' => $hasHcm,
        ]);

        return $content;
    }

    /**
     * Get current user's username
     */
    static function getUsername(): string
    {
        if (!self::isLoggedIn()) {
            return '';
        }

        return self::$data['username'];
    }

    /**
     * Get current user's display name
     */
    static function getDisplayName(): string
    {
        if (!self::isLoggedIn()) {
            return '';
        }

        return self::$data['publicname'] ?? self::$data['username'];
    }

    /**
     * Normalize username
     *
     * May return an empty string.
     */
    static function normalizeUsername(string $username): string
    {
        return StringHelper::slugify($username, ['lower' => false, 'max_len' => 24]);
    }

    /**
     * Normalize display name
     *
     * May return an empty string.
     */
    static function normalizePublicname(string $publicname): string
    {
        return Html::cut(_e(StringHelper::trimExtraWhitespace($publicname)), 24);
    }

    /**
     * See if the given name is available (not already in use by another username or display name)
     */
    static function isNameAvailable(string $name, ?int $ignoredUserId = null): bool
    {
        $where = '(username=' . DB::val($name) . ' OR publicname=' . DB::val($name) . ')';

        if ($ignoredUserId !== null) {
            $where .= ' AND id!=' . DB::val($ignoredUserId);
        }

        return DB::count('user', $where) === 0;
    }

    /**
     * See if the given e-mail address is available
     */
    static function isEmailAvailable(string $email, ?int $ignoredUserId = null): bool
    {
        $where = 'email=' . DB::val($email);

        if ($ignoredUserId !== null) {
            $where .= ' AND id!=' . DB::val($ignoredUserId);
        }

        return DB::count('user', $where) === 0;
    }

    /**
     * Compose parts of SQL query to load user data
     *
     * @param string|null $joinUserIdColumn name of user ID column to use for joining or NULL (= don't join)
     * @param string $prefix user data column prefix
     * @param string $alias alias of the joined user table
     * @param mixed $emptyValue column value that signifies "no user"
     * @return array{columns: string[], column_list: string, joins: string, alias: string, prefix: string}
     */
    static function createQuery(?string $joinUserIdColumn = null, string $prefix = 'user_', string $alias = 'u', $emptyValue = -1): array
    {
        $groupAlias = "{$alias}g";

        // column array
        $columns = [
            "{$alias}.id" => "{$prefix}id",
            "{$alias}.username" => "{$prefix}username",
            "{$alias}.publicname" => "{$prefix}publicname",
            "{$alias}.group_id" => "{$prefix}group_id",
            "{$alias}.avatar" => "{$prefix}avatar",
            "{$groupAlias}.title" => "{$prefix}group_title",
            "{$groupAlias}.icon" => "{$prefix}group_icon",
            "{$groupAlias}.level" => "{$prefix}group_level",
            "{$groupAlias}.color" => "{$prefix}group_color",
        ];

        // joins
        $joins = [];

        if ($joinUserIdColumn !== null) {
            $joins[] = 'LEFT JOIN ' . DB::table('user') . " {$alias} ON({$joinUserIdColumn}" . DB::notEqual($emptyValue) . " AND {$joinUserIdColumn}={$alias}.id)";
        }

        $joins[] = 'LEFT JOIN ' . DB::table('user_group') . " {$groupAlias} ON({$groupAlias}.id={$alias}.group_id)";

        // extend
        Extend::call('user.query', [
            'columns' => &$columns,
            'joins' => &$joins,
            'empty_value' => $emptyValue,
            'prefix' => $prefix,
            'alias' => $alias,
        ]);

        // make column list
        $columnList = '';
        $isFirstColumn = true;

        foreach ($columns as $columnName => $columnAlias) {
            if (!$isFirstColumn) {
                $columnList .= ',';
            } else {
                $isFirstColumn = false;
            }

            $columnList .= $columnName . ' ' . $columnAlias;
        }

        return [
            'columns' => array_values($columns),
            'column_list' => $columnList,
            'joins' => implode(' ', $joins),
            'alias' => $alias,
            'prefix' => $prefix,
        ];
    }

    /**
     * Delete an user
     */
    static function delete(int $id): bool
    {
        // fetch user's data
        $user = DB::queryRow('SELECT id,username,avatar FROM ' . DB::table('user') . ' WHERE id=' . DB::val($id));

        if ($user === false) {
            return false;
        }

        // extend event (check)
        $allow = true;
        $replacement = null;
        Extend::call('user.delete.check', ['user' => $user, 'allow' => &$allow, 'replacement' => &$replacement]);

        if (!$allow) {
            return false;
        }

        // get a replacement user to assign existing content to
        if ($replacement === null) {
            $replacement = DB::queryRow('SELECT id FROM ' . DB::table('user') . ' WHERE group_id=' . DB::val(self::ADMIN_GROUP_ID) . ' AND levelshift=1 AND blocked=0 AND id!=' . DB::val($id) . ' ORDER BY registertime LIMIT 1');

            if ($replacement === false) {
                return false;
            }
        }

        // extend event (before deletion)
        Extend::call('user.delete.before', ['user' => $user, 'replacement' => $replacement]);

        // delete database data
        DB::delete('user', 'id=' . DB::val($id));
        DB::query(
            'DELETE ' . DB::table('pm') . ',post'
            . ' FROM ' . DB::table('pm')
            . ' LEFT JOIN ' . DB::table('post') . ' AS post ON (post.type=' . Post::PRIVATE_MSG . ' AND post.home=' . DB::table('pm') . '.id)'
            . ' WHERE receiver=' . DB::val($id) . ' OR sender=' . DB::val($id)
        );
        DB::update('post', 'author=' . DB::val($id), [
            'guest' => sprintf('%x', crc32((string) $id)),
            'author' => -1,
        ], null);
        DB::update('article', 'author=' . DB::val($id), ['author' => $replacement['id']], null);
        DB::update('poll', 'author=' . DB::val($id), ['author' => $replacement['id']], null);

        // delete avatar
        if (isset($user['avatar'])) {
            self::removeAvatar($user['avatar']);
        }

        // log
        Logger::notice('user', sprintf('User "%s" has been deleted', $user['username']), ['user_id' => $id]);

        // extend event (after deletion)
        Extend::call('user.delete.after', ['user' => $user, 'replacement' => $replacement]);

        return true;
    }

    /**
     * Make user authentication hash
     *
     * @param string $type see User::AUTH_* constants
     * @param string $email user's e-mail
     * @param string $storedPassword user's password stored in the database
     * @param string $salt additional string data to distinguish hashes
     */
    static function getAuthHash(string $type, string $email, string $storedPassword, string $salt = ''): string
    {
        return hash_hmac('sha256', $type . '$' . $email . '$' . $storedPassword . '$' . $salt, Core::$secret);
    }

    /**
     * Login a user
     */
    static function login(int $id, string $storedPassword, string $email, bool $persistent = false): void
    {
        $_SESSION['user_id'] = $id;
        $_SESSION['user_auth'] = self::getAuthHash(self::AUTH_SESSION, $email, $storedPassword);

        if ($persistent && !headers_sent()) {
            $cookie_data = [];
            $cookie_data[] = $id;
            $cookie_data[] = self::getAuthHash(self::AUTH_PERSISTENT_LOGIN, $email, $storedPassword);

            Cookie::set(self::COOKIE_PERSISTENT_LOGIN, implode('$', $cookie_data), ['expires' => time() + 31536000]);
        }
    }

    /**
     * Render a login form
     *
     * @param bool $title render title 1/0
     * @param bool $required indicate a required login due to insufficient privileges 1/0
     * @param string|null $return return URL
     * @param bool $embedded don't render a <form> tag 1/0
     */
    static function renderLoginForm(bool $title = false, bool $required = false, ?string $return = null, bool $embedded = false): string
    {
        $output = '';

        // title
        if ($title) {
            $title_text = _lang($required ? (self::isLoggedIn() ? 'global.accessdenied' : 'login.required.title') : 'login.title');

            if (Core::$env === Core::ENV_ADMIN) {
                $output .= '<h1>' . $title_text . "</h1>\n";
            } else {
                $GLOBALS['_index']->title = $title_text;
            }
        }

        // text
        if ($required) {
            $output .= '<p>' . _lang('login.required.p') . "</p>\n";
        }

        // login result message
        if (isset($_GET['login_form_result'])) {
            $login_result = self::getLoginMessage(Request::get('login_form_result'));

            if ($login_result !== null) {
                $output .= $login_result;
            }
        }

        // content
        if (!self::isLoggedIn()) {
            // return URL
            if ($return === null && !$embedded) {
                if (isset($_GET['login_form_return'])) {
                    $return = Request::get('login_form_return');
                } else  {
                    $return = Core::getCurrentUrl()->buildRelative();
                }
            }

            // form action
            if (!$embedded) {
                // script
                $action = Router::path('system/script/login.php');
            } else {
                // embedded
                $action = null;
            }

            // add return URL to the action
            if (!empty($return)) {
                $action = UrlHelper::appendParams($action, '_return=' . urlencode($return));
            }

            // form URL
            $form_url = Core::getCurrentUrl();

            if ($form_url->has('login_form_result')) {
                $form_url->remove('login_form_result');
            }

            // render form
            $rows = [];
            $rows[] = ['label' => _lang('login.username'), 'content' => '<input type="text" name="login_username" class="inputmedium"' . Form::restoreValue($_SESSION, 'login_form_username') . ' maxlength="191" autocomplete="username" autofocus>'];
            $rows[] = ['label' => _lang('login.password'), 'content' => '<input type="password" name="login_password" class="inputmedium" autocomplete="current-password">'];

            if (!$embedded) {
                $rows[] = Form::getSubmitRow([
                    'text' => _lang('global.login'),
                    'append' => ' <label><input type="checkbox" name="login_persistent" value="1"> ' . _lang('login.persistent') . '</label>',
                ]);
            }

            $output .= Form::render(
                [
                    'name' => 'login_form',
                    'action' => $action,
                    'embedded' => $embedded,
                    'form_append' => '<input type="hidden" name="login_form_url" value="' . _e($form_url->buildRelative()) . "\">\n",
                ],
                $rows
            );

            if (isset($_SESSION['login_form_username'])) {
                unset($_SESSION['login_form_username']);
            }

            // links
            if (!$embedded) {
                $links = [];

                if (Settings::get('registration') && Core::$env === Core::ENV_WEB) {
                    $links['reg'] = ['url' => Router::module('reg'), 'text' => _lang('mod.reg')];
                }

                if (Settings::get('lostpass')) {
                    $links['lostpass'] = ['url' => Router::module('lostpass'), 'text' => _lang('mod.lostpass')];
                }

                Extend::call('user.login.links', ['links' => &$links]);

                if (!empty($links)) {
                    $output .= "<ul class=\"login-form-links\">\n";

                    foreach ($links as $link_id => $link) {
                        $output .= '<li class="login-form-link-' . $link_id . '"><a href="' . _e($link['url']) . "\">{$link['text']}</a></li>\n";
                    }

                    $output .= "</ul>\n";
                }
            }
        } else {
            $output .= '<p>' . _lang('login.ininfo') . ' <em>' . self::getUsername() . '</em> - <a href="' . _e(Xsrf::addToUrl(Router::path('system/script/logout.php'))) . '">' . _lang('usermenu.logout') . '</a>.</p>';
        }

        return $output;
    }

    /**
     * Get login message for the given code
     *
     * @param int $code see User::LOGIN_* constants
     */
    static function getLoginMessage(int $code): ?Message
    {
        switch ($code) {
            case self::LOGIN_FAILURE:
                return Message::warning(_lang('login.failure'));
            case self::LOGIN_SUCCESS:
                return Message::ok(_lang('login.success'));
            case self::LOGIN_BLOCKED:
                return Message::warning(_lang('login.blocked.message'));
            case self::LOGIN_REMOVED:
                return Message::ok(_lang('login.selfremove'));
            case self::LOGIN_ATTEMPTS_EXCEEDED:
                return Message::warning(_lang('login.attemptlimit', ['%minutes%' => _num(Settings::get('maxloginexpire') / 60)]));
            case self::LOGIN_XSRF_FAILURE:
                return Message::error(_lang('xsrf.msg'));
            default:
                return Extend::fetch('user.login.message', ['code' => $code]);
        }
    }

    /**
     * Handle a login request
     *
     * Returns a message code for {@see User::getLoginMessage()).
     */
    static function submitLogin(string $username, string $plainPassword, bool $persistent = false): int
    {
        // already logged in?
        if (self::isLoggedIn()) {
            return self::LOGIN_FAILURE;
        }

        // XSRF check
        if (!Xsrf::check()) {
            return self::LOGIN_XSRF_FAILURE;
        }

        // login attempt limit check
        if (!IpLog::check(IpLog::FAILED_LOGIN_ATTEMPT)) {
            return self::LOGIN_ATTEMPTS_EXCEEDED;
        }

        // check username
        if ($username === '') {
            return self::LOGIN_FAILURE;
        }

        // extend event (before)
        $extend_result = null;
        Extend::call('user.login.before', [
            'username' => $username,
            'password' => $plainPassword,
            'persistent' => $persistent,
            'result' => &$extend_result,
        ]);

        if ($extend_result !== null) {
            return $extend_result;
        }

        // load user
        if (strpos($username, '@') !== false) {
            $cond = 'u.email=' . DB::val($username);
        } else {
            $cond = 'u.username=' . DB::val($username) . ' OR u.publicname=' . DB::val($username);
        }

        $query = DB::queryRow('SELECT u.id,u.username,u.email,u.logincounter,u.password,u.blocked,g.blocked group_blocked FROM ' . DB::table('user') . ' u JOIN ' . DB::table('user_group') . ' g ON(u.group_id=g.id) WHERE ' . $cond);

        if ($query === false) {
            // user not found
            return self::LOGIN_FAILURE;
        }

        // check password
        $password = Password::load($query['password']);

        if (!$password->match($plainPassword)) {
            IpLog::update(IpLog::FAILED_LOGIN_ATTEMPT);
            Logger::notice('security', sprintf('Failed login attempt for user "%s"', $query['username']), ['user_id' => $query['id']]);

            return self::LOGIN_FAILURE;
        }

        // check blocked status
        if ($query['blocked'] || $query['group_blocked']) {
            return self::LOGIN_BLOCKED;
        }

        // update user data
        $changeset = [
            'ip' => Core::getClientIp(),
            'activitytime' => time(),
            'logincounter' => $query['logincounter'] + 1,
        ];

        if ($password->shouldUpdate()) {
            // update password
            $password->update($plainPassword);
            $changeset['password'] = $query['password'] = $password->build();
        }

        DB::update('user', 'id=' . DB::val($query['id']), $changeset);

        // extend event
        Extend::call('user.login', ['user' => $query]);

        // login
        self::login($query['id'], $query['password'], $query['email'], $persistent);

        // all ok
        return self::LOGIN_SUCCESS;
    }

    /**
     * Logout the current user
     *
     * @param bool $destroy destroy session 1/0
     */
    static function logout(bool $destroy = true): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        Extend::call('user.logout');

        $_SESSION = [];

        if ($destroy) {
            session_destroy();

            if (!headers_sent()) {
                Cookie::remove(self::COOKIE_SESSION);
            }
        }

        if (!headers_sent() && Cookie::exists(self::COOKIE_PERSISTENT_LOGIN)) {
            Cookie::remove(self::COOKIE_PERSISTENT_LOGIN);
        }

        return true;
    }

    /**
     * Get number of unread private messages for the current user
     *
     * The result is cached.
     */
    static function getUnreadPmCount(): int
    {
        static $result = null;

        if ($result === null) {
            $result = DB::count('pm', '(receiver=' . self::getId() . ' AND receiver_deleted=0 AND receiver_readtime<update_time) OR (sender=' . self::getId() . ' AND sender_deleted=0 AND sender_readtime<update_time)');
        }

        return (int) $result;
    }

    /**
     * Upload a new avatar
     *
     * Returns avatar UID or NULL on failure.
     */
    static function uploadAvatar(
        string $source,
        string $originalFilename,
        ?array $limits = null,
        ?ImageException &$exception = null
    ): ?string {
        $uid = StringGenerator::generateUniqueHash();

        return ImageService::process(
            'avatar',
            $source,
            self::getAvatarPath($uid),
            [
                'limits' => $limits,
                'resize' => [
                    'mode' => ImageTransformer::RESIZE_FILL,
                    'w' => 128,
                    'h' => 128,
                ],
                'write' => [
                    'jpg_quality' => 95,
                ],
                'format' => ImageLoader::getFormat($originalFilename),
            ],
            $exception
        )
            ? $uid
            : null;
    }

    /**
     * Get path to user avatar
     */
    static function getAvatarPath(string $avatar): string
    {
        return ImageStorage::getPath('images/avatars/', $avatar, 'jpg', 1);
    }

    /**
     * Render user avatar
     *
     * Supported options:
     * ------------------
     * - get_path (0)     return avatar path instead of HTML 1/0
     * - default (1)      use default avatar if user has none, otherwise return NULL 1/0
     * - default_dark (-) use dark variant of the default avatar 1/0 (default depends on current template)
     * - link (1)         link to the user's profile 1/0
     * - extend (1)       enable extend event 1/0
     * - class (-)        custom CSS class
     *
     * @param array $data user data (avatar, username, publicname)
     * @param array{
     *     get_path?: bool,
     *     default?: bool,
     *     default_dark?: bool|null,
     *     link?: bool,
     *     extend?: bool,
     *     class?: string|null,
     * } $options see description
     */
    static function renderAvatar(array $data, array $options = []): ?string
    {
        $options += [
            'get_url' => false,
            'default' => true,
            'default_dark' => null,
            'link' => true,
            'extend' => true,
            'class' => null,
        ];

        if ($options['extend']) {
            Extend::call('user.avatar', [
                'data' => &$data,
                'options' => $options,
            ]);
        }

        $hasAvatar = ($data['avatar'] !== null);

        if ($hasAvatar) {
            $avatarPath = self::getAvatarPath($data['avatar']);
        } else {
            $avatarPath = Template::getCurrent()->getDirectory() . '/images/no-avatar.jpg';
        }

        $url = Router::file($avatarPath);

        // allow custom avatar rendering by plugins
        if ($options['extend']) {
            $extendOutput = Extend::buffer('user.avatar.render', [
                'data' => $data,
                'url' => &$url,
                'options' => $options,
            ]);

            if ($extendOutput !== '') {
                return $extendOutput;
            }
        }

        // return NULL if user has no avatar and default avatar is disabled
        if (!$options['default'] && !$hasAvatar) {
            return null;
        }

        // return only the URL?
        if ($options['get_url']) {
            return $url;
        }

        // render
        $out = '';

        if ($options['link']) {
            $out .= '<a href="' . _e(Router::module('profile', ['query' => ['id' =>  $data['username']]])) . '">';
        }

        $out .= '<img class="avatar' . ($options['class'] !== null ? " {$options['class']}" : '') . '" src="' . _e($url) . '" alt="' . $data[$data['publicname'] !== null ? 'publicname' : 'username'] . '">';

        if ($options['link']) {
            $out .= '</a>';
        }

        return $out;
    }

    /**
     * Render avatar based on data fetched by {@see self::createQuery()}
     *
     * @param array $userQuery output of {@see self::createQuery()}
     * @param array $row result row
     * @param array $options render options, {@see self::renderAvatar()}
     */
    static function renderAvatarFromQuery(array $userQuery, array $row, array $options = []): string
    {
        $userData = Arr::getSubset($row, $userQuery['columns'], strlen($userQuery['prefix']));

        return self::renderAvatar($userData, $options);
    }

    /**
     * Remove user avatar
     */
    static function removeAvatar(string $avatar): bool
    {
        return @unlink(self::getAvatarPath($avatar));
    }

    /**
     * Render a form to repeat a POST request
     *
     * @param bool $allow_login allow a login, if the user is not logged in 1/0
     * @param Message|null $login_message custom login message
     * @param string|null $target_url form's target URL (null = current URL)
     * @param bool $do_repeat send to the final URL 1/0
     */
    static function renderPostRepeatForm(bool $allow_login = true, ?Message $login_message = null, ?string $target_url = null, bool $do_repeat = false): string
    {
        if ($target_url === null) {
            $target_url = Core::getCurrentUrl()->buildRelative();
        }

        if ($do_repeat) {
            $action = $target_url;
        } else {
            $action = Router::path('system/script/post_repeat.php', ['query' => ['login' => ($allow_login ? '1' : '0'), '_return' => $target_url]]);
        }

        $output = '<form name="post_repeat" method="post" action="' . _e($action). "\">\n";
        $output .= Form::renderHiddenPostInputs(null, $allow_login ? 'login_' : null);

        if ($allow_login && !self::isLoggedIn()) {
            if ($login_message === null) {
                $login_message = Message::ok(_lang('post_repeat.login'));
            }

            $login_message->append('<div class="hr"><hr></div>' . self::renderLoginForm(false, false, null, true), true);

            $output .= $login_message;
        } elseif ($login_message !== null) {
            $output .= $login_message;
        }

        $output .= '<p><input type="submit" value="' . _lang($do_repeat ? 'post_repeat.submit' : 'global.continue') . '"></p>';
        $output .= Xsrf::getInput() . "</form>\n";

        return $output;
    }
}
