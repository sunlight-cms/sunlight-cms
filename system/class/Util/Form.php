<?php

namespace Sunlight\Util;

use Sunlight\Extend;
use Sunlight\Xsrf;

abstract class Form
{
    /**
     * Zaskrtnout checkbox na zaklade podminky
     *
     * @param bool $input
     * @return string
     */
    static function activateCheckbox($input)
    {
        return $input ? ' checked' : '';
    }

    /**
     * Nacteni odeslaneho checkboxu formularem (POST)
     *
     * @param string $name jmeno checkboxu (post)
     * @return int 1/0
     */
    static function loadCheckbox($name)
    {
        return isset($_POST[$name]) ? 1 : 0;
    }

    /**
     * Zakazat pole formulare, pokud NEPLATI podminka
     *
     * @param bool $cond pole je povoleno 1/0
     * @return string
     */
    static function disableInputUnless($cond)
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
     * @param string $name    nazev checkboxu
     * @param bool   $default vychozi stav
     * @param string $method  POST/GET
     * @return string
     */
    static function restoreChecked($key_var, $name, $default = false, $method = 'POST')
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
     * @param string $name    nazev checkboxu
     * @param bool   $default vychozi stav
     * @param string $method  POST/GET
     * @return string
     */
    static function restoreCheckedAndName($key_var, $name, $default = false, $method = 'POST')
    {
        return ' name="' . $name . '"' . static::restoreChecked($key_var, $name, $default, $method);
    }

    /**
     * Obnoveni hodnoty prvku podle stavu $_POST
     *
     * @param string      $name          nazev klice v post
     * @param string|null $else          vychozi hodnota
     * @param bool        $param         vykreslit jako atribut ' value=".."' 1/0
     * @param bool        $else_entities escapovat hodnotu $else 1/0
     * @return string
     */
    static function restorePostValue($name, $else = null, $param = true, $else_entities = true)
    {
        return static::restoreValue($_POST, $name, $else, $param, $else_entities);
    }

    /**
     * Nastaveni nazvu prvku a obnoveni hodnoty z $_POST
     *
     * @param string      $name          nazev klice
     * @param string|null $else          vychozi hodnota
     * @param bool        $else_entities escapovat hodnotu $else 1/0
     * @return string
     */
    static function restorePostValueAndName($name, $else = null, $else_entities = true)
    {
        return ' name="' . $name . '"' . static::restorePostValue($name, $else, true, $else_entities);
    }

    /**
     * Obnoveni hodnoty prvku podle stavu $_GET
     *
     * @param string      $name          nazev klice
     * @param string|null $else          vychozi hodnota
     * @param bool        $param         vykreslit jako atribut ' value=".."' 1/0
     * @param bool        $else_entities escapovat hodnotu $else 1/0
     * @return string
     */
    static function restoreGetValue($name, $else = null, $param = true, $else_entities = true)
    {
        return static::restoreValue($_GET, $name, $else, $param, $else_entities);
    }

    /**
     * Nastaveni nazvu prvku a obnoveni hodnoty z $_GET
     *
     * @param string      $name          nazev klice
     * @param string|null $else          vychozi hodnota
     * @param bool        $else_entities escapovat hodnotu $else 1/0
     * @return string
     */
    static function restoreGetValueAndName($name, $else = null, $else_entities = true)
    {
        return ' name="' . $name . '"' . static::restoreGetValue($name, $else, true, $else_entities);
    }

    /**
     * Obnoveni hodnoty prvku na zaklade hodnoty z pole
     *
     * @param array       $values        pole s hodnotami
     * @param string      $key           nazev klice
     * @param string|null $else          vychozi hodnota
     * @param bool        $param         vykreslit jako atribut ' value=".."' 1/0
     * @param bool        $else_entities escapovat hodnotu $else 1/0
     * @return string
     */
    static function restoreValue(array $values, $key, $else = null, $param = true, $else_entities = true)
    {
        if (isset($values[$key]) && is_scalar($values[$key])) {
            $value = _e((string) $values[$key]);
        } else {
            $value = ($else_entities ? _e($else) : $else);
        }

        if ($param) {
            if ($value !== null && $value !== '') {
                return ' value="' . $value . '"';
            } else {
                return '';
            }
        } else {
            return $value;
        }
    }

    /**
     * Vykreslit aktualni POST data jako serii skrytych formularovych prvku
     *
     * XSRF token je automaticky vynechan.
     *
     * @see \Sunlight\Util\Arr::filterKeys()
     *
     * @param string|null $include
     * @param string|null $exclude
     * @param array       $excludeList
     * @return string
     */
    static function renderHiddenPostInputs($include = null, $exclude = null, array $excludeList = array())
    {
        $excludeList[] = '_security_token';

        return static::renderHiddenInputs(Arr::filterKeys($_POST, $include, $exclude, $excludeList));
    }

    /**
     * Vykreslit dana data jako serii skrytych formularovych prvku
     *
     * @param array $data data
     * @return string
     */
    static function renderHiddenInputs(array $data)
    {
        $output = '';
        $counter = 0;

        foreach ($data as $key => $value) {
            if ($counter > 0) {
                $output .= "\n";
            }
            $output .= static::renderHiddenInput($key, $value);
            ++$counter;
        }

        return $output;
    }

    /**
     * Vykreslit 1 nebo vice skrytych prvku formulare pro danou hodnotu
     *
     * @param string $key   aktualni klic
     * @param mixed  $value hodnota
     * @param array  $pkeys nadrazene klice
     * @return string
     */
    static function renderHiddenInput($key, $value, array $pkeys = array())
    {
        if (is_array($value)) {
            // pole
            $output = '';
            $counter = 0;

            foreach($value as $vkey => $vvalue) {
                if ($counter > 0) {
                    $output .= "\n";
                }
                $output .= static::renderHiddenInput($key, $vvalue, array_merge($pkeys, array($vkey)));
                ++$counter;
            }

            return $output;
        } else {
            // hodnota
            $name = _e($key);
            if (!empty($pkeys)) {
                $name .= _e('[' . implode('][', $pkeys) . ']');
            }

            return "<input type='hidden' name='" . $name . "' value='" . _e($value) . "'>";
        }
    }

    /**
     * Sestavit kod inputu pro vyber casu
     *
     * @param string        $name             identifikator casove hodnoty
     * @param int|null|bool $timestamp        cas, -1 (= aktualni) nebo null (= nevyplneno)
     * @param bool          $updatebox        zobrazit checkbox pro nastaveni na aktualni cas pri ulozeni
     * @param bool          $updateboxchecked zaskrtnuti checkboxu 1/0
     * @return string
     */
    static function editTime($name, $timestamp = null, $updatebox = false, $updateboxchecked = false)
    {
        $output = Extend::buffer('time.edit', array(
            'timestamp' => $timestamp,
            'updatebox' => $updatebox,
            'updatebox_checked' => $updateboxchecked,
        ));

        if ($output === '') {
            if (-1 === $timestamp) {
                $timestamp = time();
            }
            if ($timestamp !== null) {
                $timestamp = getdate($timestamp);
            } else {
                $timestamp = array('seconds' => '', 'minutes' => '', 'hours' => '', 'mday' => '', 'mon' => '', 'year' => '');
            }
            $output .= "<input type='text' size='2' maxlength='2' name='{$name}[tday]' value='" . $timestamp['mday'] . "'>.<input type='text' size='2' maxlength='2' name='{$name}[tmonth]' value='" . $timestamp['mon'] . "'> <input type='text' size='4' maxlength='4' name='{$name}[tyear]' value='" . $timestamp['year'] . "'> <input type='text' size='2' maxlength='2' name='{$name}[thour]' value='" . $timestamp['hours'] . "'>:<input type='text' size='2' maxlength='2' name='{$name}[tminute]' value='" . $timestamp['minutes'] . "'>:<input type='text' size='2' maxlength='2' name='{$name}[tsecond]' value='" . $timestamp['seconds'] . "'> <small>" . _lang('time.help') . "</small>";
            if ($updatebox) {
                $output .= " <label><input type='checkbox' name='{$name}[tupdate]' value='1'" . static::activateCheckbox($updateboxchecked) . "> " . _lang('time.update') . "</label>";
            }
        }

        return $output;
    }

    /**
     * Nacist casovou hodnotu vytvorenou a odeslanou pomoci {@see Form::editTime()}
     *
     * @param string $name    identifikator casove hodnoty
     * @param int    $default vychozi casova hodnota pro pripad chyby
     * @return int|null
     */
    static function loadTime($name, $default = null)
    {
        $result = Extend::fetch('time.load', array(
            'name' => $name,
            'default' => $default,
        ));

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
     *
     * submit_text (*)      popisek odesilaciho tlacitka (vychozi je _lang('global.send'))
     * submit_append (-)    vlastni HTML vlozene za odesilaci tlacitko
     * submit_span (0)      roztahnout bunku s odesilacim tlacitkem na cely radek (tzn. zadna mezera vlevo)
     * submit_name (-)      name atribut odesilaciho tlacitka
     * submit_row (-)       1 vlastni pole s atributy radku s odesilacim tlacitkem (viz format zaznamu v $rows)
     *                      uvedeni teto volby potlaci submit_text, submit_span a submit_name
     *
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
     *
     * @param array $options parametry formulare (viz popis funkce)
     * @param array $rows    pole s radky (viz popis funkce)
     * @return string
     */
    static function render(array $options, array $rows)
    {
        // vychozi parametry
        $options += array(
            'name' => null,
            'method' => 'post',
            'action' => null,
            'autocomplete' => null,
            'enctype' => null,
            'multipart' => false,
            'id' => null,
            'class' => isset($options['name']) ? $options['name'] : null,
            'embedded' => false,
            'submit_text' => _lang('global.send'),
            'submit_append' => '',
            'submit_span' => false,
            'submit_name' => null,
            'submit_row' => null,
            'table_prepend' => '',
            'table_append' => '',
            'form_prepend' => '',
            'form_append' => '',
        );
        if ($options['multipart']) {
            $options['enctype'] = 'multipart/form-data';
        }

        // extend
        $extend_buffer = Extend::buffer('form.output', array(
            'options' => &$options,
            'rows' => &$rows,
        ));

        if ($extend_buffer !== '') {
            // vykresleni pretizeno
            return $extend_buffer;
        }

        // vykresleni
        $output = '';

        // form tag
        if (!$options['embedded']) {
            $output .= '<form';

            foreach (array('name', 'method', 'action', 'enctype', 'id', 'class', 'autocomplete') as $attr) {
                if ($options[$attr] !== null) {
                    $output .= ' ' . $attr . '="' . _e($options[$attr]) . '"';
                }
            }

            $output .= ">\n";
        }
        $output .= $options['form_prepend'];

        // zacatek tabulky
        $output .= "<table>\n";
        $output .= $options['table_prepend'];

        // radky
        foreach ($rows as $row) {
            $output .= static::renderRow($row);
        }

        // radek s odesilacim tlacitkem
        if (!$options['embedded']) {
            if ($options['submit_row'] !== null) {
                $submit_row = $options['submit_row'];
            } else {
                $submit_row = array(
                    'label' => $options['submit_span'] ? null : '',
                    'content' => '<input type="submit"' . (!empty($options['submit_name']) ? ' name="' . _e($options['submit_name']) . '"' : '') . ' value="' . _e($options['submit_text']) . '">',
                );
            }
            if (isset($submit_row['content'])) {
                $submit_row['content'] .= $options['submit_append'];
            }
            $output .= static::renderRow($submit_row);
        } elseif (!empty($options['submit_append'])) {
            $output .= static::renderRow(array(
                'label' => $options['submit_span'] ? null : '',
                'content' => $options['submit_append'],
            ));
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
     * Vykreslit radek tabulky formulare
     *
     * @return string
     */
    protected static function renderRow(array $row)
    {
        $row += array(
            'label' => null,
            'content' => null,
            'top' => false,
            'class' => '',
        );
        if ($row['top']) {
            $row['class'] .= ($row['class'] !== '' ? ' ' : '') . 'valign-top';
        }

        // prazdny radek?
        if (empty($row['label']) && empty($row['content'])) {
            return '';
        }

        // zacatek radku
        $output = '<tr' . ($row['class'] !== '' ? " class=\"{$row['class']}\"" : '') . ">\n";

        // popisek
        if ($row['label'] !== null) {
            $output .= "<th>{$row['label']}</th>\n";
        }

        // obsah
        $output .= '<td';
        if ($row['label'] === null) {
            $output .= ' colspan="2"';
        }
        $output .= ">{$row['content']}</td>\n";

        // konec radku
        $output .= "</tr>\n";

        return $output;
    }
}
