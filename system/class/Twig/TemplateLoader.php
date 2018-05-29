<?php

namespace Sunlight\Twig;

class TemplateLoader extends \Twig_Loader_Filesystem
{
    protected $overrides;

    /**
     * Override a template
     *
     * @param string $name
     * @param string $newName
     */
    function override($name, $newName)
    {
        $this->overrides[$name] = $newName;
    }

    protected function normalizeName($name)
    {
        $name = parent::normalizeName($name);

        // bypass overrides if template name starts with a "!"
        if (isset($name[0]) && $name[0] === '!') {
            return substr($name, 1);
        }

        // check overrides
        if (isset($this->overrides[$name])) {
            return $this->overrides[$name];
        }

        // use original name
        return $name;
    }
}
