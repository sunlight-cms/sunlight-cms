<?php

namespace Sunlight\Util;

use Sunlight\Extend;
use Sunlight\Xsrf;

abstract class Form
{
    /**
     * Activate a checkbox based on a condition
     */
    static function activateCheckbox(bool $checked): string
    {
        return $checked ? ' checked' : '';
    }

    /**
     * Check if a checkbox was submitted in POST data
     *
     * @return int 1 or 0
     */
    static function loadCheckbox(string $name): int
    {
        return isset($_POST[$name]) ? 1 : 0;
    }

    /**
     * Disable an input unless a condition is TRUE
     */
    static function disableInputUnless(bool $enabled): string
    {
        if (!$enabled) {
            return ' disabled';
        }

        return '';
    }

    /**
     * Restore checkbox state using POST or GET data
     *
     * @param string $key_var name of another input that indicates that the form has been submitted
     * @param string $name checkbox name
     * @param bool $default default state
     * @param string $method POST/GET
     */
    static function restoreChecked(string $key_var, string $name, bool $default = false, string $method = 'POST'): string
    {
        if (
            $method === Request::method()
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
     * Set checkbox name and restore state using POST or GET data
     *
     * @param string $key_var name of another input that indicates that the form has been submitted
     * @param string $name checkbox name
     * @param bool $default default state
     * @param string $method POST/GET
     */
    static function restoreCheckedAndName(string $key_var, string $name, bool $default = false, string $method = 'POST'): string
    {
        return ' name="' . $name . '"' . self::restoreChecked($key_var, $name, $default, $method);
    }

    /**
     * Restore input value from POST data
     *
     * @param string $name input name
     * @param string|null $else default value
     * @param bool $param output a "value" attribute instead of just the value 1/0
     * @param bool $else_entities escape HTML in $else 1/0
     */
    static function restorePostValue(string $name, ?string $else = null, bool $param = true, bool $else_entities = true): string
    {
        return self::restoreValue($_POST, $name, $else, $param, $else_entities);
    }

    /**
     * Set input name and restore value from POST data
     *
     * @param string $name input name
     * @param string|null $else default value
     * @param bool $else_entities escape HTML in $else 1/0
     */
    static function restorePostValueAndName(string $name, ?string $else = null, bool $else_entities = true): string
    {
        return ' name="' . $name . '"' . self::restorePostValue($name, $else, true, $else_entities);
    }

    /**
     * Restore input value from GET data
     *
     * @param string $name input name
     * @param string|null $else default value
     * @param bool $param output a "value" attribute instead of just the value 1/0
     * @param bool $else_entities escape HTML in $else 1/0
     */
    static function restoreGetValue(string $name, ?string $else = null, bool $param = true, bool $else_entities = true): string
    {
        return self::restoreValue($_GET, $name, $else, $param, $else_entities);
    }

    /**
     * Set input name and restore value from GET data
     *
     * @param string $name input name
     * @param string|null $else default value
     * @param bool $else_entities escape HTML in $else 1/0
     */
    static function restoreGetValueAndName(string $name, ?string $else = null, bool $else_entities = true): string
    {
        return ' name="' . $name . '"' . self::restoreGetValue($name, $else, true, $else_entities);
    }

    /**
     * Restore input value based on submitted data
     *
     * @param array $values submitted data
     * @param string $key input name
     * @param string|null $else default value
     * @param bool $param output a "value" attribute instead of just the value 1/0
     * @param bool $else_entities escape HTML in $else 1/0
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
     * Render current POST data as hidden inputs
     *
     * XSRF token is excluded.
     *
     * @see Arr::filterKeys()
     */
    static function renderHiddenPostInputs(?string $includedPrefix = null, ?string $excludedPrefix = null, array $excludedKeys = []): string
    {
        $excludedKeys[] = '_security_token';

        return self::renderHiddenInputs(Arr::filterKeys($_POST, $includedPrefix, $excludedPrefix, $excludedKeys));
    }

    /**
     * Render data as hidden inputs
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
     * Render 1 or more hidden inputs
     */
    static function renderHiddenInput(string $key, $value, array $parentKeys = []): string
    {
        if (is_array($value)) {
            // array
            $output = '';
            $counter = 0;

            foreach ($value as $vkey => $vvalue) {
                if ($counter > 0) {
                    $output .= "\n";
                }

                $output .= self::renderHiddenInput($key, $vvalue, array_merge($parentKeys, [$vkey]));
                ++$counter;
            }

            return $output;
        }

        // value
        $name = _e($key);

        if (!empty($parentKeys)) {
            $name .= _e('[' . implode('][', $parentKeys) . ']');
        }

        return '<input type="hidden" name="' . $name . '" value="' . _e($value) . '">';
    }

    /**
     * Render inputs for date-time selection
     *
     * @param string $name input name
     * @param int|null $timestamp pre-filled date and time value
     * @param bool $updatebox allow setting the date and time value to current 1/0
     * @param bool $updateboxchecked enable setting date and time value to current by default 1/0
     */
    static function editTime(string $name, ?int $timestamp = null, bool $updatebox = false, bool $updateboxchecked = false): string
    {
        $output = Extend::buffer('time.edit', [
            'timestamp' => $timestamp,
            'updatebox' => $updatebox,
            'updatebox_checked' => $updateboxchecked,
        ]);

        if ($output === '') {
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
     * Load date-time value submitted by {@see Form::editTime()}
     *
     * @param string $name input name
     * @param int|null $default default in case of invalid value
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
     * Render a form
     *
     * Supported $options:
     * --------------------------------------------------------------
     * name (-)             name attribute
     * method (post)        method attribute
     * action (-)           action attribute
     * autocomplete (-)     autocomplete attribute
     * enctype (-)          enctype attribute
     * multipart (0)        set enctype to "multipart/form-data"
     * id (-)               id attribute
     * class (-)            class attribute
     * embedded (0)         don't render <form> tag and XSRF input
     * table_attrs          custom HTML at the end of the <table> tag
     * table_append         custom HTML before </table>
     * form_append          custom HTML before </form>
     *
     * Format of a single row in $rows:
     * -----------------------------------------
     * label (-)        row albel
     * content (-)      row content
     * top (0)          align the row to the top
     * class (-)        custom <tr> attribute
     *
     * - if both label and content is empty, the row is skipped
     * - if label is null, the content cell will span the entire row
     * - use {@see Form::getSubmitRow()} to add a submit button
     *
     *
     * @param array[] $rows
     */
    static function render(array $options, array $rows): string
    {
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
            // rendered by plugin
            return $extend_buffer;
        }

        //  render
        $output = '';

        // <form>
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

        // <table>
        $output .= "<table{$options['table_attrs']}>\n";
        $output .= $options['table_prepend'];

        // rows
        $useColspan = self::anyRowHasLabel($rows);

        foreach ($rows as $row) {
            $output .= self::renderRow($row, $useColspan);
        }

        // </table>
        $output .= $options['table_append'];
        $output .= "</table>\n";

        // </form>
        $output .= $options['form_append'];

        if (!$options['embedded']) {
            $output .= Xsrf::getInput();
            $output .= "\n</form>\n";
        }

        return $output;
    }

    /**
     * Create a form row with a submit button
     *
     * Supported $options:
     * -------------------------------------
     * label ('')   row label
     * name (-)     submit button name
     * text         submit button text
     * append       HTML after submit button
     */
    static function getSubmitRow(array $options = []): array
    {
        return [
            'label' => array_key_exists('label', $options) ? $options['label'] : '',
            'content' => '<input type="submit"'
                . (isset($options['name']) ? ' name="' . _e($options['name']) . '"' : '')
                . ' value="' . _e($options['text'] ?? _lang('global.send')) . '">'
                . ($options['append'] ?? ''),
            '_submit' => true, // mark the row for plugin purposes
        ];
    }

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

        // skip empty rows
        if (empty($row['label']) && empty($row['content'])) {
            return '';
        }

        // <tr>
        $output = '<tr' . ($row['class'] !== '' ? ' class="' . $row['class'] . '"' : '') . ">\n";

        // label
        if ($row['label'] !== null) {
            $output .= "<th>{$row['label']}</th>\n";
        }

        // content
        $output .= '<td';

        if ($row['label'] === null && $useColspan) {
            $output .= ' colspan="2"';
        }

        $output .= ">{$row['content']}</td>\n";

        // </tr>
        $output .= "</tr>\n";

        return $output;
    }

    private static function anyRowHasLabel(array $rows): bool
    {
        foreach ($rows as $row) {
            if (isset($row['label'])) {
                return true;
            }
        }

        return false;
    }
}
