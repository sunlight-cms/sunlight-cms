<?php

namespace Sunlight\Util;

/**
 * PHP template
 *
 * Fills placeholders inside a PHP file template.
 *
 * Supported placeholders:
 *
 *      '@@foo.bar@@'
 *      '@@foo.bar|default@@'
 *
 *  The default value can be numeric, null, true, false
 *  or begin with "array(". Anything else will be parsed
 *  as a string literal.
 *
 *  To force a string like 'true', put double quotes around
 *  it, like so:
 *
 *      '@@foo.bar|"true"@@'
 */
class PhpTemplate
{
    /** @var string */
    protected $template;

    /**
     * @param string $template
     */
    function __construct($template)
    {
        $this->template = $template;
    }

    /**
     * Create from file
     *
     * @param string $path
     * @return static
     */
    static function fromFile($path)
    {
        Filesystem::ensureFileExists($path);

        return new static(file_get_contents($path));
    }

    /**
     * Compile the template
     *
     * @param array $vars key => value pairs
     * @return string php code
     */
    function compile(array $vars)
    {
        $that = $this;

        return preg_replace_callback('{\'@@([a-zA-Z._\-]+)(?:\|(".+"|.+))?@@\'}', function (array $matches) use ($vars, $that) {
            return $that->compilePlaceholder(
                $matches[1],
                isset($matches[2]) ? $matches[2] : null,
                $vars
            );
        }, $this->template);
    }

    /**
     * Compile a placeholder
     *
     * @param string      $name    placeholder name
     * @param string|null $default default value
     * @param array       $vars    variables
     * @return string php code
     */
    function compilePlaceholder($name, $default, array $vars)
    {
        if (key_exists($name, $vars)) {
            // provided value
            $php = var_export($vars[$name], true);
        } else {
            // no value
            if ($default !== null) {
                $php = $this->compileDefault($default);
            } else {
                $php = "''";
            }
        }

        return $php;
    }

    /**
     * Compile default value
     *
     * @param string $default
     * @return string php code
     */
    protected function compileDefault($default)
    {
        if (
            $default === 'true'
            || $default === 'false'
            || $default === 'null'
            || preg_match('{(0x)?[0-9]+(\.[0-9]+)?$}AD', $default)
        ) {
            // keywords, numbers
            return $default;
        } elseif (strncmp($default, 'array(', 6) === 0) {
            // arrays
            return str_replace("\\'", "'", $default);
        } elseif (preg_match('{"(.+)"$}AD', $default, $match)) {
            // quoted string
            return var_export($match[1], true);
        } else {
            // everything else is a string
            return var_export($default, true);
        }
    }
}
