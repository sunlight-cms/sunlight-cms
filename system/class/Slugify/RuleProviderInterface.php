<?php

namespace Sunlight\Slugify;

interface RuleProviderInterface
{
    
    function getRules(string $ruleset): array;
}
