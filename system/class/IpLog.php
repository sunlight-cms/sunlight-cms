<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;

abstract class IpLog
{
    /**
     * IP log entry for failed login attempt
     *
     * var: none
     */
    const FAILED_LOGIN_ATTEMPT = 1;

    /**
     * IP log entry for article read counter cooldown
     *
     * var: article ID
     */
    const ARTICLE_READ = 2;

    /**
     * IP log entry for article rating cooldown
     *
     * var: article ID
     */
    const ARTICLE_RATED = 3;

    /**
     * IP log entry for poll vote cooldown
     *
     * var: poll ID
     */
    const POLL_VOTE = 4;

    /**
     * IP log entry anti-spam cooldown
     *
     * var: none
     */
    const ANTI_SPAM = 5;

    /**
     * IP log entry for failed account activation attempt
     *
     * var: none
     */
    const FAILED_ACCOUNT_ACTIVATION = 6;

    /**
     * IP log entry for password reset request
     */
    const PASSWORD_RESET_REQUESTED = 7;
    
    /**
     * Zkontrolovat log IP adres
     *
     * @param int $type typ zaznamu, viz class konstanty
     * @param mixed $var promenny argument dle typu
     * @param int|null $expires doba expirace zaznamu v sekundach pro typ 8+
     */
    static function check(int $type, $var = null, ?int $expires = null): bool
    {
        if ($var !== null) {
            $var = (int) $var;
        }

        // vycisteni iplogu
        static $cleaned = [
            'system' => false,
            'custom' => [],
        ];
        if ($type <= self::PASSWORD_RESET_REQUESTED) {
            if (!$cleaned['system']) {
                DB::query('DELETE FROM ' . DB::table('iplog') . ' WHERE (type=1 AND ' . time() . '-time>' . Settings::get('maxloginexpire') . ') OR (type=2 AND ' . time() . '-time>' . Settings::get('artreadexpire') . ') OR (type=3 AND ' . time() . '-time>' . Settings::get('artrateexpire') . ') OR (type=4 AND ' . time() . '-time>' . Settings::get('pollvoteexpire') . ') OR (type=5 AND ' . time() . '-time>' . Settings::get('antispamtimeout') . ') OR (type=6 AND ' . time() . '-time>' . Settings::get('accactexpire') . ') OR (type=7 AND ' . time() . '-time>' . Settings::get('lostpassexpire') . ')');
                $cleaned['system'] = true;
            }
        } elseif (!isset($cleaned['custom'][$type])) {
            if ($expires === null) {
                throw new \InvalidArgumentException('The "expires" argument must be specified for custom types');
            }
            DB::delete('iplog', 'type=' . $type .(($var !== null) ? ' AND var=' . $var : '') . ' AND ' . time() . '-time>' . $expires);
            $cleaned['custom'][$type] = true;
        }

        // priprava
        $result = true;
        $querybasic = 'SELECT * FROM ' . DB::table('iplog') . ' WHERE ip=' . DB::val(Core::getClientIp()) . ' AND type=' . $type;

        switch ($type) {

            case self::FAILED_LOGIN_ATTEMPT:
                $query = DB::queryRow($querybasic);
                if ($query !== false && $query['var'] >= Settings::get('maxloginattempts')) {
                    $result = false;
                }
                break;

            case self::ARTICLE_READ:
            case self::ARTICLE_RATED:
            case self::POLL_VOTE:
                $query = DB::query($querybasic . ' AND var=' . $var);
                if (DB::size($query) != 0) {
                    $result = false;
                }
                break;

            case self::ANTI_SPAM:
            case self::PASSWORD_RESET_REQUESTED:
                $query = DB::query($querybasic);
                if (DB::size($query) != 0) {
                    $result = false;
                }
                break;

            case self::FAILED_ACCOUNT_ACTIVATION:
                $query = DB::queryRow($querybasic);
                if ($query !== false && $query['var'] >= 5) {
                    $result = false;
                }
                break;

            default:
                $query = DB::query($querybasic . (($var !== null) ? ' AND var=' . $var : ''));
                if (DB::size($query) != 0) {
                    $result = false;
                }
                break;
        }

        Extend::call('iplog.check', [
            'type' => $type,
            'var' => $var,
            'result' => &$result,
        ]);

        return $result;
    }

    /**
     * Aktualizace logu IP adres
     *
     * @see IpLog::check()
     *
     * @param int $type typ zaznamu
     * @param mixed $var promenny argument dle typu
     */
    static function update(int $type, $var = null): void
    {
        if ($var !== null) {
            $var = (int) $var;
        }

        $querybasic = 'SELECT * FROM ' . DB::table('iplog') . ' WHERE ip=' . DB::val(Core::getClientIp()) . ' AND type=' . $type;

        switch ($type) {

            case self::FAILED_LOGIN_ATTEMPT:
                $query = DB::queryRow($querybasic);
                if ($query !== false) {
                    DB::update('iplog', 'id=' . $query['id'], ['var' => ($query['var'] + 1)]);
                } else {
                    DB::insert('iplog', [
                        'ip' => Core::getClientIp(),
                        'type' => self::FAILED_LOGIN_ATTEMPT,
                        'time' => time(),
                        'var' => 1
                    ]);
                }
                break;

            case self::ARTICLE_READ:
                DB::insert('iplog', [
                    'ip' => Core::getClientIp(),
                    'type' => self::ARTICLE_READ,
                    'time' => time(),
                    'var' => $var
                ]);
                break;

            case self::ARTICLE_RATED:
                DB::insert('iplog', [
                    'ip' => Core::getClientIp(),
                    'type' => self::ARTICLE_RATED,
                    'time' => time(),
                    'var' => $var
                ]);
                break;

            case self::POLL_VOTE:
                DB::insert('iplog', [
                    'ip' => Core::getClientIp(),
                    'type' => self::POLL_VOTE,
                    'time' => time(),
                    'var' => $var
                ]);
                break;

            case self::ANTI_SPAM:
            case self::PASSWORD_RESET_REQUESTED:
                DB::insert('iplog', [
                    'ip' => Core::getClientIp(),
                    'type' => $type,
                    'time' => time(),
                    'var' => 0
                ]);
                break;

            case self::FAILED_ACCOUNT_ACTIVATION:
                $query = DB::queryRow($querybasic);
                if ($query !== false) {
                    DB::update('iplog', 'id=' . $query['id'], ['var' => ($query['var'] + 1)]);
                } else {
                    DB::insert('iplog', [
                        'ip' => Core::getClientIp(),
                        'type' => self::FAILED_ACCOUNT_ACTIVATION,
                        'time' => time(),
                        'var' => 1
                    ]);
                }
                break;

            default:
                $query = DB::queryRow($querybasic . (($var !== null) ? ' AND var=' . $var : ''));
                if ($query !== false) {
                    DB::update('iplog', 'id=' . $query['id'], ['time' => time()]);
                } else {
                    DB::insert('iplog', [
                        'ip' => Core::getClientIp(),
                        'type' => $type,
                        'time' => time(),
                        'var' => $var
                    ]);
                }
                break;
        }
    }
}
