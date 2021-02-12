<?php

namespace Sunlight\Slugify;

interface RuleProviderInterface
{
    /**
     * @param string $ruleset
     * @return array
     */
    function getRules(string $ruleset): array;
}
