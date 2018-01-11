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
    public function override($name, $newName)
    {
        $this->overrides[$name] = $newName;
    }

    protected function normalizeName($name)
    {
        $name = parent::normalizeName($name);

        if (isset($this->overrides[$name])) {
            return $this->overrides[$name];
        }

        return $name;
    }
}
