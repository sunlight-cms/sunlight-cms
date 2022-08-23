<?php

namespace Sunlight\Util;

use Sunlight\Extend;
use Sunlight\Xsrf;

abstract class Form
{
    /**
     * Zaskrtnout checkbox na zaklade podminky
     */
    static function activateCheckbox(bool $input): string
    {
        return $input ? ' checked' : '';
    }

    /**
     * Nacteni odeslaneho checkboxu formularem (POST)
     *
     * @param string $name jmeno checkboxu (post)
     * @return int 1/0
     */
    static function loadCheckbox(string $name): int
    {
        return isset($_POST[$name]) ? 1 : 0;
    }

    /**
     * Zakazat pole formulare, pokud NEPLATI podminka
     *
     * @param bool $cond pole je povoleno 1/0
     */
    static function disableInputUnless(bool $cond): string
    {
        if (!$cond) {
            return ' disabled';
        }

        return '';
    }

    /**
     * Obnovit stav zaskrtnuti na zaklade POST/GET dat
     *
     * @param string $key_var nazev klice, ktery indikuje odeslani daneho formulare
     * @param string $name nazev checkboxu
     * @param bool $default vychozi stav
     * @param string $method POST/GET
     */
    static function restoreChecked(string $key_var, string $name, bool $default = false, string $method = 'POST'): string
    {
        if (
            $method === $_SERVER['REQUEST_METHOD']
            && (
                $method === 'POST' && isset($_POST[$key_var], $_POST[$name])
                || $method === 'GET' && isset($_GET[$key_var], $_GET[$name])
            )
        ) {
            $active = true;
        } else {
            $active = $default;
        }

        return $active ? ' checked' : '';
    }

    /**
     * Nastavit nazev prvku a obnovit stav zaskrtnuti na zaklade POST/GET dat
     *
     * @param string $key_var nazev klice, ktery indikuje odeslani daneho formulare
     * @param string $name nazev checkboxu
     * @param bool $default vychozi stav
     * @param string $method POST/GET
     */
    static function restoreCheckedAndName(string $key_var, string $name, bool $default = false, string $method = 'POST'): string
    {
        return ' name="' . $name . '"' . self::restoreChecked($key_var, $name, $default, $method);
    }

    /**
     * Obnoveni hodnoty prvku podle stavu $_POST
     *
     * @param string $name nazev klice v post
     * @param string|null $else vychozi hodnota
     * @param bool $param vykreslit jako atribut ' value=".."' 1/0
     * @param bool $else_entities escapovat hodnotu $else 1/0
     */
    static function restorePostValue(string $name, ?string $else = null, bool $param = true, bool $else_entities = true): string
    {
        return self::restoreValue($_POST, $name, $else, $param, $else_entities);
    }

    /**
     * Nastaveni nazvu prvku a obnoveni hodnoty z $_POST
     *
     * @param string $name nazev klice
     * @param string|null $else vychozi hodnota
     * @param bool $else_entities escapovat hodnotu $else 1/0
     */
    static function restorePostValueAndName(string $name, ?string $else = null, bool $else_entities = true): string
    {
        return ' name="' . $name . '"' . self::restorePostValue($name, $else, true, $else_entities);
    }

    /**
     * Obnoveni hodnoty prvku podle stavu $_GET
     *
     * @param string $name nazev klice
     * @param string|null $else vychozi hodnota
     * @param bool $param vykreslit jako atribut ' value=".."' 1/0
     * @param bool $else_entities escapovat hodnotu $else 1/0
     */
    static function restoreGetValue(string $name, ?string $else = null, bool $param = true, bool $else_entities = true): string
    {
        return self::restoreValue($_GET, $name, $else, $param, $else_entities);
    }

    /**
     * Nastaveni nazvu prvku a obnoveni hodnoty z $_GET
     *
     * @param string $name nazev klice
     * @param string|null $else vychozi hodnota
     * @param bool $else_entities escapovat hodnotu $else 1/0
     */
    static function restoreGetValueAndName(string $name, ?string $else = null, bool $else_entities = true): string
    {
        return ' name="' . $name . '"' . self::restoreGetValue($name, $else, true, $else_entities);
    }

    /**
     * Obnoveni hodnoty prvku na zaklade hodnoty z pole
     *
     * @param array $values pole s hodnotami
     * @param string $key nazev klice
     * @param string|null $else vychozi hodnota
     * @param bool $param vykreslit jako atribut ' value=".."' 1/0
     * @param bool $else_entities escapovat hodnotu $else 1/0
     */
    static function restoreValue(array $values, string $key, ?string $else = null, bool $param = true, bool $else_entities = true): string
    {
        if (isset($values[$key]) && is_scalar($values[$key])) {
            $value = _e((string) $values[$key]);
        } elseif ($else !== null) {
            $value = $else_entities ? _e($else) : $else;
        } else {
            $value = '';
        }

        if ($param && $value !== '') {
            return ' value="' . $value . '"';
        }

        return $value;
    }

    /**
     * Vykreslit aktualni POST data jako serii skrytych formularovych prvku
     *
     * XSRF token je automaticky vynechan.
     *
     * @see \Sunlight\Util\Arr::filterKeys()
     */
    static function renderHiddenPostInputs(?string $include = null, ?string $exclude = null, array $excludeList = []): string
    {
        $excludeList[] = '_security_token';

        return self::renderHiddenInputs(Arr::filterKeys($_POST, $include, $exclude, $excludeList));
    }

    /**
     * Vykreslit dana data jako serii skrytych formularovych prvku
     *
     * @param array $data data
     */
    static function renderHiddenInputs(array $data): string
    {
        $output = '';
        $counter = 0;

        foreach ($data as $key => $value) {
            if ($counter > 0) {
                $output .= "\n";
            }
            $output .= self::renderHiddenInput($key, $value);
            ++$counter;
        }

        return $output;
    }

    /**
     * Vykreslit 1 nebo vice skrytych prvku formulare pro danou hodnotu
     *
     * @param string $key aktualni klic
     * @param mixed $value hodnota
     * @param array $pkeys nadrazene klice
     */
    static function renderHiddenInput(string $key, $value, array $pkeys = []): string
    {
        if (is_array($value)) {
            // pole
            $output = '';
            $counter = 0;

            foreach($value as $vkey => $vvalue) {
                if ($counter > 0) {
                    $output .= "\n";
                }
                $output .= self::renderHiddenInput($key, $vvalue, array_merge($pkeys, [$vkey]));
                ++$counter;
            }

            return $output;
        }

        // hodnota
        $name = _e($key);
        if (!empty($pkeys)) {
            $name .= _e('[' . implode('][', $pkeys) . ']');
        }

        return '<input type="hidden" name="' . $name . '" value="' . _e($value) . '">';
    }

    /**
     * Sestavit kod inputu pro vyber casu
     *
     * @param string $name identifikator casove hodnoty
     * @param int|null $timestamp cas, -1 (= aktualni) nebo null (= nevyplneno)
     * @param bool $updatebox zobrazit checkbox pro nastaveni na aktualni cas pri ulozeni
     * @param bool $updateboxchecked zaskrtnuti checkboxu 1/0
     */
    static function editTime(string $name, ?int $timestamp = null, bool $updatebox = false, bool $updateboxchecked = false): string
    {
        $output = Extend::buffer('time.edit', [
            'timestamp' => $timestamp,
            'updatebox' => $updatebox,
            'updatebox_checked' => $updateboxchecked,
        ]);

        if ($output === '') {
            if ($timestamp === -1) {
                $timestamp = time();
            }
            if ($timestamp !== null) {
                $timestamp = getdate($timestamp);
            } else {
                $timestamp = ['seconds' => '', 'minutes' => '', 'hours' => '', 'mday' => '', 'mon' => '', 'year' => ''];
            }
            $output .= '<input type="text" size="2" maxlength="2" name="' . $name . '[tday]" value="' . $timestamp['mday'] . '">'
                . '.<input type="text" size="2" maxlength="2" name="' . $name . '[tmonth]" value="' . $timestamp['mon'] . '">'
                . ' <input type="text" size="4" maxlength="4" name="' . $name . '[tyear]" value="' . $timestamp['year'] . '">'
                . ' <input type="text" size="2" maxlength="2" name="' . $name . '[thour]" value="' . $timestamp['hours'] . '">'
                . ':<input type="text" size="2" maxlength="2" name="' . $name . '[tminute]" value="' . $timestamp['minutes'] . '">'
                . ':<input type="text" size="2" maxlength="2" name="' . $name . '[tsecond]" value="' . $timestamp['seconds'] . '">'
                . ' <small>' . _lang('time.help') . '</small>';
            if ($updatebox) {
                $output .= ' <label><input type="checkbox" name="' . $name . '[tupdate]" value="1"' . self::activateCheckbox($updateboxchecked) . '> ' . _lang('time.update') . '</label>';
            }
        }

        return $output;
    }

    /**
     * Nacist casovou hodnotu vytvorenou a odeslanou pomoci {@see Form::editTime()}
     *
     * @param string $name identifikator casove hodnoty
     * @param int|null $default vychozi casova hodnota pro pripad chyby
     */
    static function loadTime(string $name, ?int $default = null): ?int
    {
        $result = Extend::fetch('time.load', [
            'name' => $name,
            'default' => $default,
        ]);

        if ($result === null) {
            if (!isset($_POST[$name]) || !is_array($_POST[$name])) {
                $result = $default;
            } elseif (!isset($_POST[$name]['tupdate'])) {
                $day = (int) $_POST[$name]['tday'];
                $month = (int) $_POST[$name]['tmonth'];
                $year = (int) $_POST[$name]['tyear'];
                $hour = (int) $_POST[$name]['thour'];
                $minute = (int) $_POST[$name]['tminute'];
                $second = (int) $_POST[$name]['tsecond'];
                if (checkdate($month, $day, $year) && $hour >= 0 && $hour < 24 && $minute >= 0 && $minute < 60 && $second >= 0 && $second < 60) {
                    $result = mktime($hour, $minute, $second, $month, $day, $year);
                } else {
                    $result =  $default;
                }
            } else {
                $result = time();
            }
        }

        return $result;
    }

    /**
     * Sestaveni formulare
     *
     * Mozne klice v $options:
     * -----------------------
     * name (-)             name atribut formulare
     * method (post)        method atribut formulare
     * action (-)           action atribut formulare
     * autocomplete (1)     autocomplete atribut formulare (on/off)
     * enctype (-)          enctype atribut formulare
     * multipart (0)        nastavit enctype na "multipart/form-data"
     * id (-)               id atribut formulare
     * class (-)            class atribut formulare
     * embedded (0)         nevykreslovat <form> tag ani submit radek 1/0
     * table_attrs          vlastni HTML vlozene na konec <table> tagu
     * table_append         vlastni HTML vlozene pred </table>
     * form_append          vlastni HTML vlozene pred </form>
     *
     * Format $cells:
     * --------------
     *  array(
     *      array(
     *          label       => popisek
     *          content     => obsah
     *          top         => zarovnani nahoru 1/0
     *          class       => class atribut pro <tr>
     *      ),
     *      ...
     *  )
     *
     * - radek je preskocen, pokud je obsah i popisek prazdny
     * - pokud je label null, zabere bunka s obsahem cely radek
     * - {@see Form::getSubmitRow()}
     *
     * @param array $options parametry formulare (viz popis funkce)
     * @param array $rows pole s radky (viz popis funkce)
     */
    static function render(array $options, array $rows): string
    {
        // vychozi parametry
        $options += [
            'name' => null,
            'method' => 'post',
            'action' => null,
            'autocomplete' => null,
            'enctype' => null,
            'multipart' => false,
            'id' => null,
            'class' => $options['name'] ?? null,
            'embedded' => false,
            'table_attrs' => '',
            'table_prepend' => '',
            'table_append' => '',
            'form_prepend' => '',
            'form_append' => '',
        ];
        
        // extend
        $extend_buffer = Extend::buffer('form.output', [
            'options' => &$options,
            'rows' => &$rows,
        ]);
        
        if ($options['multipart']) {
            $options['enctype'] = 'multipart/form-data';
        }

        if ($extend_buffer !== '') {
            // vykresleni pretizeno
            return $extend_buffer;
        }

        // vykresleni
        $output = '';

        // form tag
        if (!$options['embedded']) {
            $output .= '<form';

            foreach (['name', 'method', 'action', 'enctype', 'id', 'class', 'autocomplete'] as $attr) {
                if ($options[$attr] !== null) {
                    $output .= ' ' . $attr . '="' . _e($options[$attr]) . '"';
                }
            }

            $output .= ">\n";
        }
        $output .= $options['form_prepend'];

        // zacatek tabulky
        $output .= "<table{$options['table_attrs']}>\n";
        $output .= $options['table_prepend'];

        // radky
        $useColspan = self::rowsHaveLabel($rows);

        foreach ($rows as $row) {
            $output .= self::renderRow($row, $useColspan);
        }

        // konec tabulky
        $output .= $options['table_append'];
        $output .= "</table>\n";

        // konec formulare
        $output .= $options['form_append'];
        if (!$options['embedded']) {
            $output .= Xsrf::getInput();
            $output .= "\n</form>\n";
        }

        return $output;
    }

    /**
     * Sestavit radek s odesilacim tlacitkem
     */
    static function getSubmitRow(array $options = []): array
    {
        return [
            'label' => array_key_exists('label', $options) ? $options['label'] : '',
            'content' => '<input type="submit"'
                . (isset($options['name']) ? ' name="' . _e($options['name']) . '"' : '')
                . ' value="' . _e($options['text'] ?? _lang('global.send')) . '">'
                . ($options['append'] ?? ''),
            '_submit' => true, // oznaceni pro ucely pluginu
        ];
    }

    /**
     * Vykreslit radek tabulky formulare
     */
    private static function renderRow(array $row, bool $useColspan): string
    {
        $row += [
            'label' => null,
            'content' => null,
            'top' => false,
            'class' => '',
        ];
        if ($row['top']) {
            $row['class'] .= ($row['class'] !== '' ? ' ' : '') . 'valign-top';
        }

        // prazdny radek?
        if (empty($row['label']) && empty($row['content'])) {
            return '';
        }

        // zacatek radku
        $output = '<tr' . ($row['class'] !== '' ? ' class="' . $row['class'] : '') . "\">\n";

        // popisek
        if ($row['label'] !== null) {
            $output .= "<th>{$row['label']}</th>\n";
        }

        // obsah
        $output .= '<td';
        if ($row['label'] === null && $useColspan) {
            $output .= ' colspan="2"';
        }
        $output .= ">{$row['content']}</td>\n";

        // konec radku
        $output .= "</tr>\n";

        return $output;
    }

    private static function rowsHaveLabel(array $rows): bool
    {
        foreach ($rows as $row) {
            if (isset($row['label'])) {
                return true;
            }
        }

        return false;
    }
}
