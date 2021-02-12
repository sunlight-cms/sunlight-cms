<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Exception\ContentPrivilegeException;
use Sunlight\Util\Arr;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\Url;
use Sunlight\Util\UrlHelper;

abstract class User
{
    /**
     * Vratit pole se jmeny vsech existujicich opravneni
     *
     * @return array
     */
    static function listPrivileges(): array
    {
        static $extended = false;
        static $privileges = [
            'level',
            'administration',
            'adminsettings',
            'adminplugins',
            'adminusers',
            'admingroups',
            'admincontent',
            'adminother',
            'adminpages',
            'adminsection',
            'admincategory',
            'adminbook',
            'adminseparator',
            'admingallery',
            'adminlink',
            'admingroup',
            'adminforum',
            'adminpluginpage',
            'adminart',
            'adminallart',
            'adminchangeartauthor',
            'adminpoll',
            'adminpollall',
            'adminsbox',
            'adminbox',
            'adminconfirm',
            'adminautoconfirm',
            'fileaccess',
            'fileglobalaccess',
            'fileadminaccess',
            'adminhcm',
            'adminhcmphp',
            'adminbackup',
            'adminmassemail',
            'adminposts',
            'changeusername',
            'unlimitedpostaccess',
            'locktopics',
            'stickytopics',
            'movetopics',
            'postcomments',
            'artrate',
            'pollvote',
            'selfremove',
        ];

        if (!$extended) {
            Extend::call('user.privileges', ['privileges' => &$privileges]);
            $extended = true;
        }

        return $privileges;
    }

    /**
     * Zjistit, zda uzivatel ma dane pravo
     *
     * @param string $name nazev prava
     * @return bool
     */
    static function hasPrivilege(string $name): bool
    {
        $constant = '_priv_' . $name;

        return defined($constant) && constant($constant);
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
        return _logged_in && (_priv_level > $targetUserLevel || $targetUserId == _user_id);
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
        return (_logged_in || $public) && _priv_level >= $level;
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
        if (!_priv_fileaccess) {
            throw new \RuntimeException('User has no filesystem access');
        }

        if (_priv_fileglobalaccess) {
            if ($getTopmost && _priv_fileadminaccess) {
                $homeDir = _root;
            } else {
                $homeDir = _upload_dir;
            }
        } else {
            $subPath = 'home/' . _user_name . '/';
            Extend::call('user.home_dir', ['subpath' => &$subPath]);
            $homeDir = _upload_dir . $subPath;
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
            && ($dirPath = static::checkPath($dirPath, false, true)) !== false
            && is_dir($dirPath)
        ) {
            return $dirPath;
        } else {
            return static::getHomeDir();
        }
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
        if (_priv_fileaccess) {
            $path = Filesystem::parsePath($path, $isFile);
            $homeDirPath = Filesystem::parsePath(static::getHomeDir(true));

            if (
                /* nepovolit vystup z rootu */                  substr_count($path, '..') <= substr_count(_root, '..')
                /* nepovolit vystup z domovskeho adresare */    && strncmp($homeDirPath, $path, strlen($homeDirPath)) === 0
                /* nepovolit praci s nebezpecnymi soubory */    && (!$isFile || static::checkFilename(basename($path)))
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
     * Pro cesty je funkce {@see User::checkPath()}.
     *
     * @param string $filename
     * @return bool
     */
    static function checkFilename(string $filename): bool
    {
        return
            _priv_fileaccess
            && (
                _priv_fileadminaccess
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
            $joins[] = 'LEFT JOIN ' . _user_table . " {$alias} ON({$joinUserIdColumn}" . DB::notEqual($emptyValue) . " AND {$joinUserIdColumn}={$alias}.id)";
        }
        $joins[] = 'LEFT JOIN ' . _user_group_table . " {$groupAlias} ON({$groupAlias}.id={$alias}.group_id)";

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
        // nacist jmeno
        if ($id == _super_admin_id) {
            return false;
        }
        $udata = DB::queryRow("SELECT username,avatar FROM " . _user_table . " WHERE id=" . $id);
        if ($udata === false) {
            return false;
        }

        // udalost
        $allow = true;
        Extend::call('user.delete', ['id' => $id, 'username' => $udata['username'], 'allow' => &$allow]);
        if (!$allow) {
            return false;
        }

        // vyresit vazby
        DB::delete(_user_table, 'id=' . $id);
        DB::query("DELETE " . _pm_table . ",post FROM " . _pm_table . " LEFT JOIN " . _comment_table . " AS post ON (post.type=" . _post_pm . " AND post.home=" . _pm_table . ".id) WHERE receiver=" . $id . " OR sender=" . $id);
        DB::update(_comment_table, 'author=' . $id, [
            'guest' => sprintf('%x', crc32((string) $id)),
            'author' => -1,
        ]);
        DB::update(_article_table, 'author=' . $id, ['author' => _super_admin_id]);
        DB::update(_poll_table, 'author=' . $id, ['author' => _super_admin_id]);

        // odstraneni uploadovaneho avataru
        if (isset($udata['avatar'])) {
            @unlink(Picture::get('images/avatars/', $udata['avatar'], 'jpg', 1));
        }

        return true;
    }

    /**
     * Sestavit autentifikacni hash uzivatele
     *
     * @param string $storedPassword heslo ulozene v databazi
     * @return string
     */
    static function getAuthHash(string $storedPassword): string
    {
        return hash('sha512', $storedPassword);
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
        $authHash = static::getAuthHash($storedPassword);

        $_SESSION['user_id'] = $id;
        $_SESSION['user_auth'] = $authHash;

        if ($persistent && !headers_sent()) {
            $cookie_data = [];
            $cookie_data[] = $id;
            $cookie_data[] = static::getPersistentLoginHash($id, $authHash, $email);

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
            $title_text = _lang($required ? (_logged_in ? 'global.accessdenied' : 'login.required.title') : 'login.title');
            if (_env === Core::ENV_ADMIN) {
                $output .= '<h1>' . $title_text . "</h1>\n";
            } else {
                $GLOBALS['_index']['title'] = $title_text;
            }
        }

        // text
        if ($required) {
            $output .= '<p>' . _lang('login.required.p') . "</p>\n";
        }

        // zpravy
        if (isset($_GET['login_form_result'])) {
            $login_result = static::getLoginMessage(Request::get('login_form_result'));
            if ($login_result !== null) {
                $output .= $login_result;
            }
        }

        // obsah
        if (!_logged_in) {

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
                $action = Router::generate('system/script/login.php');
            } else {
                // vlozeny formular
                $action = null;
            }
            if (!empty($return)) {
                $action = UrlHelper::appendParams($action, '_return=' . rawurlencode($return));
            }

            // adresa formulare
            $form_url = Url::current();
            if ($form_url->has('login_form_result')) {
                $form_url->remove('login_form_result');
            }
            $form_append .= "<input type='hidden' name='login_form_url' value='" . _e($form_url->generate(false)) . "'>\n";

            // kod formulare
            $output .= Form::render(
                [
                    'name' => 'login_form',
                    'action' => $action,
                    'embedded' => $embedded,
                    'submit_text' => _lang('global.login'),
                    'submit_append' => " <label><input type='checkbox' name='login_persistent' value='1'> " . _lang('login.persistent') . "</label>",
                    'form_append' => $form_append,
                ],
                [
                    ['label' => _lang('login.username'), 'content' => "<input type='text' name='login_username' class='inputmedium'" . Form::restoreValue($_SESSION, 'login_form_username') . " maxlength='24' autofocus>"],
                    ['label' => _lang('login.password'), 'content' => "<input type='password' name='login_password' class='inputmedium'>"]
                ]
            );

            if (isset($_SESSION['login_form_username'])) {
                unset($_SESSION['login_form_username']);
            }

            // odkazy
            if (!$embedded) {
                $links = [];
                if (_registration && _env === Core::ENV_WEB) {
                    $links['reg'] = ['url' => Router::module('reg'), 'text' => _lang('mod.reg')];
                }
                if (_lostpass) {
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
            $output .= "<p>" . _lang('login.ininfo') . " <em>" . _user_name . "</em> - <a href='" . _e(Xsrf::addToUrl(Router::generate('system/script/logout.php'))) . "'>" . _lang('usermenu.logout') . "</a>.</p>";
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
                return Message::warning(_lang('login.attemptlimit', ['*1*' => _maxloginattempts, '*2*' => _maxloginexpire / 60]));
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
     * @return int kod {@see User::getLoginMessage())
     */
    static function submitLogin(string $username, string $plainPassword, bool $persistent = false): int
    {
        // jiz prihlasen?
        if (_logged_in) {
            return 0;
        }

        // XSRF kontrola
        if (!Xsrf::check()) {
            return 6;
        }

        // kontrola limitu
        if (!IpLog::check(_iplog_failed_login_attempt)) {
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

        $query = DB::queryRow("SELECT u.id,u.username,u.email,u.logincounter,u.password,u.blocked,g.blocked group_blocked FROM " . _user_table . " u JOIN " . _user_group_table . " g ON(u.group_id=g.id) WHERE " . $cond);
        if ($query === false) {
            // uzivatel nenalezen
            return 0;
        }

        // kontrola hesla
        $password = Password::load($query['password']);

        if (!$password->match($plainPassword)) {
            // nespravne heslo
            IpLog::update(_iplog_failed_login_attempt);

            return 0;
        }

        // kontrola blokace
        if ($query['blocked'] || $query['group_blocked']) {
            // uzivatel nebo skupina je blokovana
            return 2;
        }

        // aktualizace dat uzivatele
        $changeset = [
            'ip' => _user_ip,
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

        DB::update(_user_table, 'id=' . DB::val($query['id']), $changeset);

        // extend udalost
        Extend::call('user.login', ['user' => $query]);

        // prihlaseni
        static::login($query['id'], $query['password'], $query['email'], $persistent);

        // vse ok, uzivatel byl prihlasen
        return 1;
    }

    /**
     * Sestavit HASH pro trvale prihlaseni uzivatele
     *
     * @param int    $id
     * @param string $authHash
     * @param string $email
     * @return string
     */
    static function getPersistentLoginHash(int $id, string $authHash, string $email): string
    {
        return hash_hmac(
            'sha512',
            $id . '$' . $authHash . '$' . $email,
            Core::$secret
        );
    }

    /**
     * Odhlaseni aktualniho uzivatele
     *
     * @param bool $destroy uplne znicit session
     * @return bool
     */
    static function logout(bool $destroy = true): bool
    {
        if (!_logged_in) {
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
            $result = DB::count(_pm_table, "(receiver=" . _user_id . " AND receiver_deleted=0 AND receiver_readtime<update_time) OR (sender=" . _user_id . " AND sender_deleted=0 AND sender_readtime<update_time)");
        }

        return (int) $result;
    }

    /**
     * Ziskat kod avataru daneho uzivatele
     *
     * Mozne klice v $options
     * ----------------------
     * get_path (0)     ziskat pouze cestu namisto html kodu obrazku 1/0
     * default (1)      vratit vychozi avatar, pokud jej uzivatel nema nastaven 1/0 (jinak null)
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
            $avatarPath = Picture::get('images/avatars/', $data['avatar'], 'jpg', 1, false);
        } else {
            $avatarPath = 'images/avatars/no-avatar' . (Template::getCurrent()->getOption('dark') ? '-dark' : '') . '.jpg';
        }

        $url = Router::generate($avatarPath);

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
            $out .= '<a href="' . _e(Router::module('profile', 'id=' .  $data['username'])) . '">';
        }
        $out .= "<img class=\"avatar" . ($options['class'] !== null ? " {$options['class']}" : '') . "\" src=\"{$url}\" alt=\"" . $data[$data['publicname'] !== null ? 'publicname' : 'username'] . "\">";
        if ($options['link']) {
            $out .= '</a>';
        }

        return $out;
    }

    /**
     * Ziskat kod avataru daneho uzivatele na zaklade dat z funkce {@see User::createQuery()}
     *
     * @param array $userQuery vystup z {@see User::createQuery()}
     * @param array $row       radek z vysledku dotazu
     * @param array $options   nastaveni vykresleni, viz {@see User::renderAvatar()}
     * @return string
     */
    static function renderAvatarFromQuery(array $userQuery, array $row, array $options = []): string
    {
        $userData = Arr::getSubset($row, $userQuery['columns'], strlen($userQuery['prefix']));

        return static::renderAvatar($userData, $options);
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
            $action = Router::generate('system/script/post_repeat.php?login=' . ($allow_login ? '1' : '0') . '&target=' . rawurlencode($target_url));
        }

        $output = "<form name='post_repeat' method='post' action='" . _e($action) . "'>\n";
        $output .= Form::renderHiddenPostInputs(null, $allow_login ? 'login_' : null);

        if ($allow_login && !_logged_in) {
            if ($login_message === null) {
                $login_message = Message::ok(_lang('post_repeat.login'));
            }
            $login_message->append('<div class="hr"><hr></div>' . static::renderLoginForm(false, false, null, true), true);

            $output .= $login_message;
        } elseif ($login_message !== null) {
            $output .= $login_message;
        }

        $output .= "<p><input type='submit' value='" . _lang($do_repeat ? 'post_repeat.submit' : 'global.continue') . "'></p>";
        $output .= Xsrf::getInput() . "</form>\n";

        return $output;
    }
}
