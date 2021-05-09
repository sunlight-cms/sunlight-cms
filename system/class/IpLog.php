<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;

abstract class IpLog
{
    /**
     * Zkontrolovat log IP adres
     *
     * @param int      $type    typ zaznamu, viz _iplog_* konstanty
     * @param mixed    $var     promenny argument dle typu
     * @param int|null $expires doba expirace zaznamu v sekundach pro typ 8+
     * @return bool
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
        if ($type <= _iplog_password_reset_requested) {
            if (!$cleaned['system']) {
                DB::query("DELETE FROM " . _iplog_table . " WHERE (type=1 AND " . time() . "-time>" . _maxloginexpire . ") OR (type=2 AND " . time() . "-time>" . _artreadexpire . ") OR (type=3 AND " . time() . "-time>" . _artrateexpire . ") OR (type=4 AND " . time() . "-time>" . _pollvoteexpire . ") OR (type=5 AND " . time() . "-time>" . _postsendexpire . ") OR (type=6 AND " . time() . "-time>" . _accactexpire . ") OR (type=7 AND " . time() . "-time>" . _lostpassexpire . ")");
                $cleaned['system'] = true;
            }
        } elseif (!isset($cleaned['custom'][$type])) {
            if ($expires === null) {
                throw new \InvalidArgumentException('The "expires" argument must be specified for custom types');
            }
            DB::delete(_iplog_table, 'type=' . $type .(($var !== null) ? ' AND var=' . $var : '') . ' AND ' . time() . '-time>' . ((int) $expires));
            $cleaned['custom'][$type] = true;
        }

        // priprava
        $result = true;
        $querybasic = "SELECT * FROM " . _iplog_table . " WHERE ip=" . DB::val(_user_ip) . " AND type=" . $type;

        switch ($type) {

            case _iplog_failed_login_attempt:
                $query = DB::queryRow($querybasic);
                if ($query !== false && $query['var'] >= _maxloginattempts) {
                    $result = false;
                }
                break;

            case _iplog_article_read:
            case _iplog_article_rated:
            case _iplog_poll_vote:
                $query = DB::query($querybasic . " AND var=" . $var);
                if (DB::size($query) != 0) {
                    $result = false;
                }
                break;

            case _iplog_anti_spam:
            case _iplog_password_reset_requested:
                $query = DB::query($querybasic);
                if (DB::size($query) != 0) {
                    $result = false;
                }
                break;

            case _iplog_failed_account_activation:
                $query = DB::queryRow($querybasic);
                if ($query !== false && $query['var'] >= 5) {
                    $result = false;
                }
                break;

            default:
                $query = DB::query($querybasic . (($var !== null) ? " AND var=" . $var : ''));
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
     * @param int   $type typ zaznamu
     * @param mixed $var  promenny argument dle typu
     */
    static function update(int $type, $var = null): void
    {
        if ($var !== null) {
            $var = (int) $var;
        }

        $querybasic = "SELECT * FROM " . _iplog_table . " WHERE ip=" . DB::val(_user_ip) . " AND type=" . $type;

        switch ($type) {

            case _iplog_failed_login_attempt:
                $query = DB::queryRow($querybasic);
                if ($query !== false) {
                    DB::update(_iplog_table, 'id=' . $query['id'], ['var' => ($query['var'] + 1)]);
                } else {
                    DB::insert(_iplog_table, [
                        'ip' => _user_ip,
                        'type' => _iplog_failed_login_attempt,
                        'time' => time(),
                        'var' => 1
                    ]);
                }
                break;

            case _iplog_article_read:
                DB::insert(_iplog_table, [
                    'ip' => _user_ip,
                    'type' => _iplog_article_read,
                    'time' => time(),
                    'var' => $var
                ]);
                break;

            case _iplog_article_rated:
                DB::insert(_iplog_table, [
                    'ip' => _user_ip,
                    'type' => _iplog_article_rated,
                    'time' => time(),
                    'var' => $var
                ]);
                break;

            case _iplog_poll_vote:
                DB::insert(_iplog_table, [
                    'ip' => _user_ip,
                    'type' => _iplog_poll_vote,
                    'time' => time(),
                    'var' => $var
                ]);
                break;

            case _iplog_anti_spam:
            case _iplog_password_reset_requested:
                DB::insert(_iplog_table, [
                    'ip' => _user_ip,
                    'type' => $type,
                    'time' => time(),
                    'var' => 0
                ]);
                break;

            case _iplog_failed_account_activation:
                $query = DB::queryRow($querybasic);
                if ($query !== false) {
                    DB::update(_iplog_table, 'id=' . $query['id'], ['var' => ($query['var'] + 1)]);
                } else {
                    DB::insert(_iplog_table, [
                        'ip' => _user_ip,
                        'type' => _iplog_failed_account_activation,
                        'time' => time(),
                        'var' => 1
                    ]);
                }
                break;

            default:
                $query = DB::queryRow($querybasic . (($var !== null) ? " AND var=" . $var : ''));
                if ($query !== false) {
                    DB::update(_iplog_table, 'id=' . $query['id'], ['time' => time()]);
                } else {
                    DB::insert(_iplog_table, [
                        'ip' => _user_ip,
                        'type' => $type,
                        'time' => time(),
                        'var' => $var
                    ]);
                }
                break;
        }
    }
}
