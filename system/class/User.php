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
use Sunlight\Util\Filesystem;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\StringManipulator;
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

    /** Auth hash type - persistent login */
    const AUTH_PERSISTENT_LOGIN = 'persistent_login';

    /** Auth hash type - session */
    const AUTH_SESSION = 'session';

    /** Auth hash type - mass email management */
    const AUTH_MASSEMAIL = 'massemail';

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

        do {
            $userData = null;
            $loginDataExist = isset($_SESSION['user_id'], $_SESSION['user_auth']);

            // check persistent login cookie if there are no login data
            if (!$loginDataExist) {
                // check cookie existence
                $persistentCookieName = Core::$appId . '_persistent_key';
                if (isset($_COOKIE[$persistentCookieName]) && is_string($_COOKIE[$persistentCookieName])) {
                    // cookie auth process
                    do {
                        // parse cookie
                        $cookie = explode('$', $_COOKIE[$persistentCookieName], 2);
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
                        // cookie authoriation has failed, remove the cookie
                        setcookie(Core::$appId . '_persistent_key', '', (time() - 3600), '/');
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
                // neplatny hash
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
            // increase level for super users
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
     * Zjistit, zda uzivatel ma stejne id
     *
     * @param int $targetUserId
     * @return bool
     */
    static function equals(int $targetUserId): bool
    {
        return $targetUserId == self::getId();
    }

    static function getLevel(): int
    {
        return self::$group['level'];
    }

    /**
     * Zjistit, zda uzivatel ma dane pravo
     *
     * @param string $name nazev prava
     * @return bool
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
     * Overit heslo uzivatele
     *
     * @param string $plainPassword
     * @return bool
     */
    static function checkPassword(string $plainPassword): bool
    {
        if (self::isLoggedIn()) {
            return Password::load(self::$data['password'])->match($plainPassword);
        }

        return false;
    }

    /**
     * Vyhodnotit pravo pristupu k cilovemu uzivateli
     *
     * @param int $targetUserId    ID ciloveho uzivatele
     * @param int $targetUserLevel uroven skupiny ciloveho uzivatele
     * @return bool
     */
    static function checkLevel(int $targetUserId, int $targetUserLevel): bool
    {
        return self::isLoggedIn() && (self::getLevel() > $targetUserLevel || self::equals($targetUserId));
    }

    /**
     * Vyhodnocenu prava aktualniho uzivatele pro pristup na zaklade verejnosti, urovne a stavu prihlaseni
     *
     * @param bool     $public polozka je verejna 1/0
     * @param int|null $level  minimalni pozadovana uroven
     * @return bool
     */
    static function checkPublicAccess(bool $public, ?int $level = 0): bool
    {
        return (self::isLoggedIn() || $public) && self::getLevel() >= $level;
    }

    /**
     * Ziskat domovsky adresar uzivatele
     *
     * Adresar NEMUSI existovat!
     *
     * @param bool $getTopmost ziskat cestu na nejvyssi mozne urovni 1/0
     * @throws \RuntimeException nejsou-li prava
     * @return string
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
     * Normalizovat cestu k adresari dle prav uzivatele
     *
     * @param string|null $dirPath
     * @return string cesta s lomitkem na konci
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
     * Zjistit, zda ma uzivatel pravo pracovat s danou cestou
     *
     * @param string $path    cesta
     * @param bool   $isFile  jedna se o soubor 1/0
     * @param bool   $getPath vratit zpracovanou cestu v pripade uspechu 1/0
     * @return bool|string true / false nebo cesta, je-li kontrola uspesna a $getPath je true
     */
    static function checkPath(string $path, bool $isFile, bool $getPath = false)
    {
        if (self::hasPrivilege('fileaccess')) {
            $path = Filesystem::parsePath($path, $isFile);
            $homeDirPath = Filesystem::parsePath(self::getHomeDir(true));

            if (
                /* nepovolit vystup z rootu */                  substr_count($path, '..') <= substr_count(SL_ROOT, '..')
                /* nepovolit vystup z domovskeho adresare */    && strncmp($homeDirPath, $path, strlen($homeDirPath)) === 0
                /* nepovolit praci s nebezpecnymi soubory */    && (!$isFile || self::checkFilename(basename($path)))
            ) {
                return $getPath ? $path : true;
            }
        }

        return false;
    }

    /**
     * Presunout soubor, ktery byl nahran uzivatelem
     *
     * @param string $path
     * @param string $newPath
     * @return bool
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
     * Zjistit, zda ma uzivatel pravo pracovat s danym nazvem souboru
     *
     * Tato funkce kontroluje NAZEV souboru, nikoliv cestu!
     * Pro cesty je funkce {@see self::checkPath()}.
     *
     * @param string $filename
     * @return bool
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
     * Filtrovat uzivatelsky obsah na zaklade opravneni
     *
     * @param string $content obsah
     * @param bool   $isHtml  jedna se o HTML kod
     * @param bool   $hasHcm  obsah muze obsahovat HCM moduly
     * @throws \LogicException
     * @throws ContentPrivilegeException
     * @return string
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
     * Ziskat uzivatelske jmeno aktualniho uzivatele
     */
    static function getUsername(): string
    {
        if (!self::isLoggedIn()) {
            return '';
        }

        return self::$data['username'];
    }

    /**
     * Ziskat jmeno aktualniho uzivatele pro zobrazeni
     */
    static function getDisplayName(): string
    {
        if (!self::isLoggedIn()) {
            return '';
        }

        return self::$data['publicname'] ?? self::$data['username'];
    }

    /**
     * Normalizovat format uzivatelskeho jmena
     *
     * Muze vratit prazdny string.
     */
    static function normalizeUsername(string $username): string
    {
        return StringManipulator::slugify(StringManipulator::cut($username, 24), false);
    }

    /**
     * Normalizovat format zobrazovaneho jmena
     *
     * Muze vratit prazdny string.
     */
    static function normalizePublicname(string $publicname): string
    {
        return Html::cut(_e(StringManipulator::trimExtraWhitespace($publicname)), 24);
    }

    /**
     * Zkontrolovat zda neni dane jmeno obsazene uzivatelskym nebo zobrazovanym jmenem
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
     * Zkontrolovat zda neni dany email obsazeny
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
     * Sestavit casti dotazu pro nacteni dat uzivatele
     *
     * Struktura navratove hodnoty:
     *
     * array(
     *      columns => array(a,b,c,...),
     *      column_list => "a,b,c,...",
     *      joins => "JOIN ...",
     *      alias => "...",
     *      prefix = "...",
     * )
     *
     * @param string|null $joinUserIdColumn nazev sloupce obsahujici ID uzivatele nebo NULL (= nejoinovat)
     * @param string      $prefix           predpona pro nazvy nacitanych sloupcu
     * @param string      $alias            alias joinovane user tabulky
     * @param mixed       $emptyValue       hodnota, ktera reprezentuje neurceneho uzivatele
     * @return array viz popis funkce
     */
    static function createQuery(?string $joinUserIdColumn = null, string $prefix = 'user_', string $alias = 'u', $emptyValue = -1): array
    {
        $groupAlias = "{$alias}g";

        // pole sloupcu
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

        // joiny
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

        // sestavit seznam sloupcu
        $columnList = '';
        $isFirstColumn = true;
        foreach ($columns as $columnName => $columnAlias) {
            if (!$isFirstColumn) {
                $columnList .= ',';
            } else {
                $isFirstColumn = false;
            }

            $columnList .= "{$columnName} {$columnAlias}";
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
     * Odstraneni uzivatele
     *
     * @param int $id id uzivatele
     * @return bool
     */
    static function delete(int $id): bool
    {
        // nacist data uzivatele
        $user = DB::queryRow("SELECT id,avatar FROM " . DB::table('user') . " WHERE id=" . DB::val($id));
        if ($user === false) {
            return false;
        }

        // udalost kontroly
        $allow = true;
        $replacement = null;
        Extend::call('user.delete.check', ['user' => $user, 'allow' => &$allow, 'replacement' => &$replacement]);
        if (!$allow) {
            return false;
        }

        // ziskat uzivatele pro prirazeni existujiciho obsahu
        if ($replacement === null) {
            $replacement = DB::queryRow('SELECT id FROM ' . DB::table('user') . ' WHERE group_id=' . DB::val(self::ADMIN_GROUP_ID) . ' AND levelshift=1 AND blocked=0 AND id!=' . DB::val($id) . ' ORDER BY registertime LIMIT 1');
            if ($replacement === false) {
                return false;
            }
        }

        // udalost pred smazanim
        Extend::call('user.delete.before', ['user' => $user, 'replacement' => $replacement]);

        // odstranit data z databaze
        DB::delete('user', 'id=' . DB::val($id));
        DB::query("DELETE " . DB::table('pm') . ",post FROM " . DB::table('pm') . " LEFT JOIN " . DB::table('post') . " AS post ON (post.type=" . Post::PRIVATE_MSG . " AND post.home=" . DB::table('pm') . ".id) WHERE receiver=" . DB::val($id) . " OR sender=" . DB::val($id));
        DB::update('post', 'author=' . DB::val($id), [
            'guest' => sprintf('%x', crc32((string) $id)),
            'author' => -1,
        ]);
        DB::update('article', 'author=' . DB::val($id), ['author' => $replacement['id']]);
        DB::update('poll', 'author=' . DB::val($id), ['author' => $replacement['id']]);

        // odstranit avatar
        if (isset($user['avatar'])) {
            self::removeAvatar($user['avatar']);
        }

        // udalost po smazani
        Extend::call('user.delete.after', ['user' => $user, 'replacement' => $replacement]);

        return true;
    }

    /**
     * Sestavit autentifikacni hash uzivatele
     *
     * @param string $type viz User::AUTH_* konstanty
     * @param string $email email uzivatele
     * @param string $storedPassword heslo ulozene v databazi
     * @return string
     */
    static function getAuthHash(string $type, string $email, string $storedPassword): string
    {
        return hash_hmac('sha256', $type . '$' . $email . '$' . $storedPassword, Core::$secret);
    }

    /**
     * Prihlaseni uzivatele
     *
     * @param int    $id
     * @param string $storedPassword
     * @param string $email
     * @param bool   $persistent
     */
    static function login(int $id, string $storedPassword, string $email, bool $persistent = false): void
    {
        $_SESSION['user_id'] = $id;
        $_SESSION['user_auth'] = self::getAuthHash(self::AUTH_SESSION, $email, $storedPassword);

        if ($persistent && !headers_sent()) {
            $cookie_data = [];
            $cookie_data[] = $id;
            $cookie_data[] = self::getAuthHash(self::AUTH_PERSISTENT_LOGIN, $email, $storedPassword);

            setcookie(
                Core::$appId . '_persistent_key',
                implode('$', $cookie_data),
                (time() + 31536000),
                '/'
            );
        }
    }

    /**
     * Sestavit kod prihlasovaciho formulare
     *
     * @param bool        $title    vykreslit titulek 1/0
     * @param bool        $required jedna se o povinne prihlaseni z duvodu nedostatku prav 1/0
     * @param string|null $return   navratova URL
     * @param bool        $embedded nevykreslovat <form> tag 1/0
     * @return string
     */
    static function renderLoginForm(bool $title = false, bool $required = false, ?string $return = null, bool $embedded = false): string
    {
        $output = '';

        // titulek
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

        // zpravy
        if (isset($_GET['login_form_result'])) {
            $login_result = self::getLoginMessage(Request::get('login_form_result'));
            if ($login_result !== null) {
                $output .= $login_result;
            }
        }

        // obsah
        if (!self::isLoggedIn()) {

            $form_append = '';

            // adresa pro navrat
            if ($return === null && !$embedded) {
                if (isset($_GET['login_form_return'])) {
                    $return = Request::get('login_form_return');
                } else  {
                    $return = $_SERVER['REQUEST_URI'];
                }
            }

            // akce formulare
            if (!$embedded) {
                // systemovy skript
                $action = Router::path('system/script/login.php');
            } else {
                // vlozeny formular
                $action = null;
            }
            if (!empty($return)) {
                $action = UrlHelper::appendParams($action, '_return=' . urlencode($return));
            }

            // adresa formulare
            $form_url = Core::getCurrentUrl();
            if ($form_url->has('login_form_result')) {
                $form_url->remove('login_form_result');
            }
            $form_append .= "<input type='hidden' name='login_form_url' value='" . _e($form_url->buildRelative()) . "'>\n";

            // kod formulare
            $rows = [];
            $rows[] = ['label' => _lang('login.username'), 'content' => "<input type='text' name='login_username' class='inputmedium'" . Form::restoreValue($_SESSION, 'login_form_username') . " maxlength='24' autocomplete='username' autofocus>"];
            $rows[] = ['label' => _lang('login.password'), 'content' => "<input type='password' name='login_password' class='inputmedium' autocomplete='current-password'>"];

            if (!$embedded) {
                $rows[] = Form::getSubmitRow([
                    'text' => _lang('global.login'),
                    'append' => " <label><input type='checkbox' name='login_persistent' value='1'> " . _lang('login.persistent') . "</label>",
                ]);
            }

            $output .= Form::render(
                [
                    'name' => 'login_form',
                    'action' => $action,
                    'embedded' => $embedded,
                    'form_append' => $form_append,
                ],
                $rows
            );

            if (isset($_SESSION['login_form_username'])) {
                unset($_SESSION['login_form_username']);
            }

            // odkazy
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
                        $output .= "<li class=\"login-form-link-{$link_id}\"><a href=\"" . _e($link['url']) . "\">{$link['text']}</a></li>\n";
                    }
                    $output .= "</ul>\n";
                }
            }

        } else {
            $output .= "<p>" . _lang('login.ininfo') . " <em>" . self::getUsername() . "</em> - <a href='" . _e(Xsrf::addToUrl(Router::path('system/script/logout.php'))) . "'>" . _lang('usermenu.logout') . "</a>.</p>";
        }

        return $output;
    }

    /**
     * Ziskat hlasku pro dany kod
     *
     * Existujici kody:
     * ------------------------------------------------------
     * 0    prihlaseni se nezdarilo (spatne jmeno nebo heslo / jiz prihlasen)
     * 1    prihlaseni uspesne
     * 2    uzivatel je blokovan
     * 3    automaticke odhlaseni z bezp. duvodu
     * 4    smazani vlastniho uctu
     * 5    vycerpan limit neuspesnych prihlaseni
     * 6    neplatny XSRF token
     *
     * @param int $code
     * @return Message|null
     */
    static function getLoginMessage(int $code): ?Message
    {
        switch ($code) {
            case 0:
                return Message::warning(_lang('login.failure'));
            case 1:
                return Message::ok(_lang('login.success'));
            case 2:
                return Message::warning(_lang('login.blocked.message'));
            case 3:
                return Message::error(_lang('login.securitylogout'));
            case 4:
                return Message::ok(_lang('login.selfremove'));
            case 5:
                return Message::warning(_lang('login.attemptlimit', ['%max_attempts%' => Settings::get('maxloginattempts'), '%minutes%' => Settings::get('maxloginexpire') / 60]));
            case 6:
                return Message::error(_lang('xsrf.msg'));
            default:
                return Extend::fetch('user.login.message', ['code' => $code]);
        }
    }

    /**
     * Zpracovat POST prihlaseni
     *
     * @param string $username
     * @param string $plainPassword
     * @param bool   $persistent
     * @return int kod {@see self::getLoginMessage())
     */
    static function submitLogin(string $username, string $plainPassword, bool $persistent = false): int
    {
        // jiz prihlasen?
        if (self::isLoggedIn()) {
            return 0;
        }

        // XSRF kontrola
        if (!Xsrf::check()) {
            return 6;
        }

        // kontrola limitu
        if (!IpLog::check(IpLog::FAILED_LOGIN_ATTEMPT)) {
            return 5;
        }

        // kontrola uziv. jmena
        if ($username === '') {
            // prazdne uziv. jmeno
            return 0;
        }

        // udalost
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

        // nalezeni uzivatele
        if (strpos($username, '@') !== false) {
            $cond = 'u.email=' . DB::val($username);
        } else {
            $cond = 'u.username=' . DB::val($username) . ' OR u.publicname=' . DB::val($username);
        }

        $query = DB::queryRow("SELECT u.id,u.username,u.email,u.logincounter,u.password,u.blocked,g.blocked group_blocked FROM " . DB::table('user') . " u JOIN " . DB::table('user_group') . " g ON(u.group_id=g.id) WHERE " . $cond);
        if ($query === false) {
            // uzivatel nenalezen
            return 0;
        }

        // kontrola hesla
        $password = Password::load($query['password']);

        if (!$password->match($plainPassword)) {
            // nespravne heslo
            IpLog::update(IpLog::FAILED_LOGIN_ATTEMPT);

            return 0;
        }

        // kontrola blokace
        if ($query['blocked'] || $query['group_blocked']) {
            // uzivatel nebo skupina je blokovana
            return 2;
        }

        // aktualizace dat uzivatele
        $changeset = [
            'ip' => Core::getClientIp(),
            'activitytime' => time(),
            'logincounter' => $query['logincounter'] + 1,
            'security_hash' => null,
            'security_hash_expires' => 0,
        ];

        if ($password->shouldUpdate()) {
            // aktualizace formatu hesla
            $password->update($plainPassword);

            $changeset['password'] = $query['password'] = $password->build();
        }

        DB::update('user', 'id=' . DB::val($query['id']), $changeset);

        // extend udalost
        Extend::call('user.login', ['user' => $query]);

        // prihlaseni
        self::login($query['id'], $query['password'], $query['email'], $persistent);

        // vse ok, uzivatel byl prihlasen
        return 1;
    }

    /**
     * Odhlaseni aktualniho uzivatele
     *
     * @param bool $destroy uplne znicit session
     * @return bool
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
                setcookie(session_name(), '', time() - 3600, '/');
            }
        }

        if (!headers_sent() && isset($_COOKIE[Core::$appId . '_persistent_key'])) {
            setcookie(Core::$appId . '_persistent_key', '', (time() - 3600), '/');
        }

        return true;
    }

    /**
     * Ziskat pocet neprectenych PM (soukromych zprav) aktualniho uzivatele
     *
     * Vystup teto funkce je cachovan.
     *
     * @return int
     */
    static function getUnreadPmCount(): int
    {
        static $result = null;

        if ($result === null) {
            $result = DB::count('pm', "(receiver=" . self::getId() . " AND receiver_deleted=0 AND receiver_readtime<update_time) OR (sender=" . self::getId() . " AND sender_deleted=0 AND sender_readtime<update_time)");
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
     * Ziskat kod avataru daneho uzivatele
     *
     * Mozne klice v $options
     * ----------------------
     * get_path (0)     ziskat pouze cestu namisto html kodu obrazku 1/0
     * default (1)      vratit vychozi avatar, pokud jej uzivatel nema nastaven 1/0 (jinak null)
     * default_dark (-) tmave tema pro vychozi avatar (vychozi je dle motivu)
     * link (1)         odkazat na profil uzivatele 1/0
     * extend (1)       vyvolat extend udalost 1/0
     * class (-)        vlastni CSS trida
     *
     * @param array $data    samostatna data uzivatele (avatar, username, publicname)
     * @param array $options moznosti vykresleni, viz popis funkce
     * @return string|null HTML kod s obrazkem nebo URL
     */
    static function renderAvatar(array $data, array $options = []): ?string
    {
        // vychozi nastaveni
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
            $avatarPath = SL_ROOT . 'images/avatars/no-avatar'
                . ($options['default_dark'] ?? Template::getCurrent()->getOption('dark') ? '-dark' : '')
                . '.jpg';
        }

        $url = Router::file($avatarPath);

        // vykresleni rozsirenim
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

        // vratit null neni-li avatar a je deaktivovan vychozi
        if (!$options['default'] && !$hasAvatar) {
            return null;
        }

        // vratit pouze URL?
        if ($options['get_url']) {
            return $url;
        }

        // vykreslit obrazek
        $out = '';
        if ($options['link']) {
            $out .= '<a href="' . _e(Router::module('profile', ['query' => ['id' =>  $data['username']]])) . '">';
        }
        $out .= "<img class=\"avatar" . ($options['class'] !== null ? " {$options['class']}" : '') . "\" src=\"{$url}\" alt=\"" . $data[$data['publicname'] !== null ? 'publicname' : 'username'] . "\">";
        if ($options['link']) {
            $out .= '</a>';
        }

        return $out;
    }

    /**
     * Ziskat kod avataru daneho uzivatele na zaklade dat z funkce {@see self::createQuery()}
     *
     * @param array $userQuery vystup z {@see self::createQuery()}
     * @param array $row       radek z vysledku dotazu
     * @param array $options   nastaveni vykresleni, viz {@see self::renderAvatar()}
     * @return string
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
     * Ziskat kod formulare pro opakovani POST requestu
     *
     * @param bool         $allow_login   umoznit znovuprihlaseni, neni-li uzivatel prihlasen 1/0
     * @param Message|null $login_message vlastni hlaska
     * @param string|null  $target_url    cil formulare (null = aktualni URL)
     * @param bool         $do_repeat     odeslat na cilovou adresu 1/0
     * @return string
     */
    static function renderPostRepeatForm(bool $allow_login = true, ?Message $login_message = null, ?string $target_url = null, bool $do_repeat = false): string
    {
        if ($target_url === null) {
            $target_url = $_SERVER['REQUEST_URI'];
        }

        if ($do_repeat) {
            $action = $target_url;
        } else {
            $action = Router::path('system/script/post_repeat.php', ['query' => ['login' => ($allow_login ? '1' : '0'), 'target' => $target_url]]);
        }

        $output = "<form name='post_repeat' method='post' action='" . _e($action) . "'>\n";
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

        $output .= "<p><input type='submit' value='" . _lang($do_repeat ? 'post_repeat.submit' : 'global.continue') . "'></p>";
        $output .= Xsrf::getInput() . "</form>\n";

        return $output;
    }
}
