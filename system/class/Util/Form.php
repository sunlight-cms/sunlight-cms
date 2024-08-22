<?php

namespace Sunlight\Util;

use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Xsrf;

abstract class Form
{
    /**
     * Check if a checkbox was submitted in POST data
     * 
     * @param bool $default default checkbox state (requires $key_var to detect form submission)
     */
    static function loadCheckbox(string $name, bool $default = false, ?string $key_var = null, bool $get = false): bool
    {
        $request = $GLOBALS[$get ? '_GET' : '_POST'];

        if ($key_var !== null && !isset($request[$key_var])) {
            return $default; // form not submitted
        }

        return isset($request[$name]);
    }

    /**
     * Render an <input>
     */
    static function input(string $type, ?string $name = null, ?string $value = null, array $attrs = [], bool $doubleEncodeValue = true): string
    {
        $output = Extend::buffer('form.input', [
            'type' => &$type,
            'name' => &$name,
            'value' => &$value,
            'attrs' => &$attrs,
            'double_encode_value' => &$doubleEncodeValue,
        ]);

        if ($output === '') {
            $output = '<input'
                . ($name !== null ? ' name="' . _e($name) . '"' : '')
                . ' type="' . _e($type) . '"'
                . ($value !== null ? ' value="' . _e($value, $doubleEncodeValue) . '"' : '')
                . GenericTemplates::renderAttrs($attrs)
                . '>';
        }

        Extend::call('form.input.after', [
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'attrs' => $attrs,
            'double_encode_value' => $doubleEncodeValue,
            'output' => &$output,
        ]);

        return $output;
    }

    /**
     * Render a <textarea>
     */
    static function textarea(?string $name, ?string $content, array $attrs = [], bool $doubleEncodeContent = true): string
    {
        $output = Extend::buffer('form.textarea', [
            'name' => &$name,
            'content' => &$content,
            'attrs' => &$attrs,
            'double_encode_content' => &$doubleEncodeContent,
        ]);

        if ($output === '') {
            $output = '<textarea'
                . ($name !== null ? ' name="' . _e($name) . '"' : '')
                . GenericTemplates::renderAttrs($attrs)
                . '>'
                . _e($content ?? '', $doubleEncodeContent)
                . '</textarea>';
        }

        Extend::call('form.textarea.after', [
            'name' => $name,
            'content' => $content,
            'attrs' => $attrs,
            'double_encode_content' => $doubleEncodeContent,
            'output' => &$output,
        ]);

        return $output;
    }

    /**
     * Render a <select>
     * 
     * @param string $name select name
     * @param array<array-key, string|array<array-key, string>> $choices value => label or optgroup => choices
     * @param array-key|array-key[]|null $selected selected choice(s) or null
     * @param array<string, scalar|null> $attrs select tag attributes
     * @param bool $doubleEncodeLabels {@see _e()}
     */
    static function select(?string $name, array $choices, $selected = null, array $attrs = [], bool $doubleEncodeLabels = true): string
    {
        $output = Extend::buffer('form.select', [
            'name' => &$name,
            'choices' => &$choices,
            'selected' => &$selected,
            'attrs' => &$attrs,
            'double_encode_labels' => &$doubleEncodeLabels,
        ]);

        if ($selected === null) {
            $selected = [];
        } elseif (is_array($selected)) {
            $selected = array_flip($selected);
        } else {
            $selected = [$selected => 0];
        }

        if ($output === '') {
            $output = '<select'
                . ($name !== null ? ' name="' . _e($name) . '"' : '')
                . GenericTemplates::renderAttrs($attrs)
                . '>';

            foreach ($choices as $k => $v) {
                if (is_array($v)) {
                    // optgroup
                    $output .= '<optgroup label="' . _e((string) $k) . "\">\n";

                    foreach ($v as $kk => $vv) {
                        $output .= '    ' . self::option(
                            $kk,
                            $vv,
                            ['selected' => isset($selected[$kk])],
                            $doubleEncodeLabels
                            
                        );
                        $output .= "\n";
                    }

                    $output .= '</optgroup>';

                } else {
                    // option
                    $output .= self::option(
                        $k,
                        $v,
                        ['selected' => isset($selected[$k])],
                        $doubleEncodeLabels
                    );
                }

                $output .= "\n";
            }

            $output .= '</select>';
        }

        Extend::call('form.select.after', [
            'name' => $name,
            'choices' => $choices,
            'selected' => $selected,
            'attrs' => $attrs,
            'double_encode_labels' => $doubleEncodeLabels,
            'output' => &$output,
        ]);

        return $output;
    }

    /**
     * Render an <option>
     */
    static function option(string $value, string $label, array $attrs = [], bool $doubleEncodeLabel = true): string
    {
        return '<option'
            . ' value="' . _e($value) . '"'
            . GenericTemplates::renderAttrs($attrs)
            . '>'
            . _e($label, $doubleEncodeLabel)
            . '</option>';
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

        return self::input('hidden', $name, $value);
    }

    /**
     * Render inputs for date-time selection
     *
     * Supported options:
     * ------------------
     * - input_class (-)          input class name
     * - now_toggle (0)           add an option to set the timestamp to current time on save 1/0
     * - now_toggle_default (0)   enable the now_toggle by default 1/0
     *
     * @param string $name input name
     * @param int|null $timestamp pre-filled timestamp
     * @param array{input_class?: string|null, now_toggle?: bool, now_toggle_default?: bool} $options see description
     */
    static function editTime(string $name, ?int $timestamp = null, array $options = []): string
    {
        $options += [
            'input_class' => null,
            'now_toggle' => false,
            'now_toggle_default' => false,
        ];

        $output = Extend::buffer('time.edit', [
            'timestamp' => $timestamp,
            'options' => &$options,
        ]);

        if ($output === '') {
            $attrs = [];
            if($options['input_class'] !== null) {
                $attrs['class'] = $options['input_class'];
            }
            $output .= self::input('datetime-local', $name, ($timestamp !== null ? date('Y-m-d\TH:i', $timestamp) : null), $attrs);

            if ($options['now_toggle']) {
                $output .= ' <label>'
                    . self::input('checkbox', $name . '_now', '1', ['checked' => (bool) $options['now_toggle_default']])
                    . _lang('time.update')
                    . '</label>';
            }
        }

        return $output;
    }

    /**
     * Load date-time value submitted by {@see Form::editTime()}
     *
     * @param string $name input name
     * @param int|null $default default in case of invalid value
     * @param bool $get load value from GET data instead of POST
     */
    static function loadTime(string $name, ?int $default = null, bool $get = false): ?int
    {
        $value = Extend::fetch('time.load', [
            'name' => $name,
            'default' => $default,
        ]);

        if ($value !== null) {
            return $value;
        }

        if (self::loadCheckbox($name . '_now')) {
            return time();
        }

        $value = $get ? Request::get($name) : Request::post($name);

        if ($value === null) {
            return $default;
        }

        $datetime = \DateTime::createFromFormat('Y-m-d\TH:i', $value) ?: \DateTime::createFromFormat('Y-m-d\TH:i:s', $value);

        if ($datetime === false) {
            return $default;
        }

        return $datetime->getTimestamp();
    }

    /**
     * Render a form
     *
     * Supported options:
     * ------------------
     * - name (-)             name attribute
     * - method (post)        method attribute
     * - action (-)           action attribute
     * - autocomplete (-)     autocomplete attribute
     * - enctype (-)          enctype attribute
     * - multipart (0)        set enctype to "multipart/form-data"
     * - id (-)               id attribute
     * - class (-)            class attribute
     * - embedded (0)         don't render <form> tag and XSRF input
     * - table_attrs          custom HTML at the end of the <table> tag
     * - table_prepend        custom after before <table>
     * - table_append         custom HTML before </table>
     * - form_prepend         custom HTML after <form>
     * - form_append          custom HTML before </form>
     *
     * Format of a single row in $rows:
     * --------------------------------
     * - label (-)        row label
     * - content (-)      row content
     * - top (0)          align the row to the top 1/0
     * - class (-)        custom <tr> class
     * - attrs (-)        array of additional <tr> attributes
     *
     * - if both label and content is empty, the row is skipped
     * - if label is null, the content cell will span the entire row
     * - use {@see Form::getSubmitRow()} to add a submit button
     *
     * @param array{
     *     name?: string|null,
     *     method?: string,
     *     action?: string|null,
     *     autocomplete?: string|null,
     *     enctype?: string|null,
     *     multipart?: bool,
     *     id?: string|null,
     *     class?: string|null,
     *     embedded?: bool,
     *     table_attrs?: string,
     *     table_prepend?: string,
     *     table_append?: string,
     *     form_prepend?: string,
     *     form_append?: string,
     * } $options see description
     *
     * @param array<array{
     *     label?: string|null,
     *     content?: string|null,
     *     top?: bool,
     *     class?: string,
     *     attrs?: array,
     * }> $rows see description
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
     * Supported options:
     * ------------------
     * - label ('')   row label
     * - name (-)     submit button name
     * - text         submit button text
     * - append       HTML after submit button
     *
     * @param array{
     *     label?: string|null,
     *     name?: string|null,
     *     text?: string|null,
     *     append?: string|null,
     * } $options see description
     */
    static function getSubmitRow(array $options = []): array
    {
        return [
            'label' => array_key_exists('label', $options) ? $options['label'] : '',
            'content' => self::input('submit', $options['name'] ?? null, $options['text'] ?? _lang('global.send'))
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
            'attrs' => [],
        ];

        // skip empty rows
        if (empty($row['label']) && empty($row['content'])) {
            return '';
        }

        // handle classes
        $classes = [];

        if ($row['top']) {
            $classes[] = 'valign-top';
        }

        if ($row['class'] !== '') {
            $classes[] = $row['class'];
        }

        if (!empty($classes)) {
            $row['attrs']['class'] = implode(' ', $classes);
        }

        // <tr>
        $output = '<tr' . GenericTemplates::renderAttrs($row['attrs']) . ">\n";

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
