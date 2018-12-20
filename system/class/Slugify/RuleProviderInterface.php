<?php

namespace Sunlight\Slugify;

interface RuleProviderInterface
{
    /**
     * @param $ruleset
     * @return array
     */
    public function getRules($ruleset);
}
