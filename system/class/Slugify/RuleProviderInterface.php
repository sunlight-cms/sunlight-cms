<?php

namespace Sunlight\Slugify;

interface RuleProviderInterface
{
    /**
     * @param $ruleset
     * @return array
     */
    function getRules($ruleset);
}
