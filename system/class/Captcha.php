<?php

namespace Sunlight;

use Sunlight\Util\Request;
use Sunlight\Util\StringGenerator;

class Captcha
{
    /**
     * Inicializace captchy
     *
     * @see \Sunlight\Util\Form::render()
     *
     * @return array radek formulare
     */
    static function init(): array
    {
        static $captchaCounter = 0;

        $output = Extend::fetch('captcha.init');
        if ($output !== null) {
            return $output;
        }

        if (_captcha && !_logged_in) {
            ++$captchaCounter;
            if (!isset($_SESSION['captcha_code']) || !is_array($_SESSION['captcha_code'])) {
                $_SESSION['captcha_code'] = [];
            }
            $_SESSION['captcha_code'][$captchaCounter] = [static::generateCode(8), false];

            return [
                'label' => _lang('captcha.input'),
                'content' => "<input type='text' name='_cp' class='inputc' autocomplete='off'><img src='" . Router::generate('system/script/captcha/image.php?n=' . $captchaCounter) . "' alt='captcha' title='" . _lang('captcha.help') . "' class='cimage'><input type='hidden' name='_cn' value='" . $captchaCounter . "'>",
                'top' => true,
                'class' => 'captcha-row',
            ];
        } else {
            return [];
        }
    }

    /**
     * Zkontrolovat vyplneni captcha fieldu
     *
     * @return bool
     */
    static function check(): bool
    {
        $result = Extend::fetch('captcha.check');

        if ($result === null) {
            // pole pro nahradu matoucich znaku
            $disambiguation = [
                '0' => 'O',
                'Q' => 'O',
                'D' => 'O',
                '1' => 'I',
                '6' => 'G',
            ];

            // kontrola
            if (_captcha and !_logged_in) {
                $enteredCode = Request::post('_cp');
                $captchaId = Request::post('_cn');

                if ($enteredCode !== null && isset($_SESSION['captcha_code'][$captchaId])) {
                    if (strtr($_SESSION['captcha_code'][$captchaId][0], $disambiguation) === strtr(mb_strtoupper($enteredCode), $disambiguation)) {
                        $result = true;
                    }
                    unset($_SESSION['captcha_code'][$captchaId]);
                }
            } else {
                $result = true;
            }
        }

        Extend::call('captcha.check.after', ['output' => &$result]);

        return $result;
    }

    /**
     * @param int $length
     * @return string
     */
    static function generateCode(int $length): string
    {
        $word = strtoupper(StringGenerator::generateWordMarkov($length));

        $maxNumbers = max(ceil($length / 3), 1);

        for ($i = 0; $i < $maxNumbers; ++$i) {
            $word[mt_rand(0, $length - 1)] = (string) mt_rand(2, 9);
        }

        return strtr($word, [
            'W' => 'X',
            'Q' => 'O',
        ]);
    }
}
