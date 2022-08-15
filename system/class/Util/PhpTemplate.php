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
    private $template;

    function __construct(string $template)
    {
        $this->template = $template;
    }

    /**
     * Create from file
     */
    static function fromFile(string $path): self
    {
        Filesystem::ensureFileExists($path);

        return new self(file_get_contents($path));
    }

    /**
     * Compile the template
     *
     * @param array $vars key => value pairs
     * @return string php code
     */
    function compile(array $vars): string
    {
        return preg_replace_callback('{\'@@([a-zA-Z._\-]+)(?:\|(".+"|.+))?@@\'}', function (array $matches) use ($vars) {
            return $this->compilePlaceholder(
                $matches[1],
                $matches[2] ?? null,
                $vars
            );
        }, $this->template);
    }

    /**
     * Compile a placeholder
     *
     * @param string $name placeholder name
     * @param string|null $default default value
     * @param array $vars variables
     * @return string php code
     */
    private function compilePlaceholder(string $name, ?string $default, array $vars): string
    {
        if (array_key_exists($name, $vars)) {
            // provided value
            $php = var_export($vars[$name], true);
        } elseif ($default !== null) {
            // no value
            $php = $this->compileDefault($default);
        } else {
            $php = "''";
        }

        return $php;
    }

    /**
     * Compile default value
     *
     * @return string php code
     */
    private function compileDefault(string $default): string
    {
        if (
            $default === 'true'
            || $default === 'false'
            || $default === 'null'
            || preg_match('{(0x)?[0-9]+(\.[0-9]+)?$}AD', $default)
        ) {
            // keywords, numbers
            return $default;
        }

        if (strncmp($default, 'array(', 6) === 0) {
            // arrays
            return str_replace("\\'", "'", $default);
        }

        if (preg_match('{"(.+)"$}AD', $default, $match)) {
            // quoted string
            return var_export($match[1], true);
        }

        // everything else is a string
        return var_export($default, true);
    }
}
