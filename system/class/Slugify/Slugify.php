<?php

namespace Sunlight\Slugify;

class Slugify
{
    /** @var self|null */
    private static $inst;
    /** @var string[] */
    private $rules = [];
    /** @var RuleProviderInterface */
    private $provider;
    /** @var array */
    private $options = [
        'regexp' => '/([^A-Za-z0-9]|-)+/',
        'separator' => '-',
        'lowercase' => true,
        'trim' => true,
        'rulesets' => [
            'default',
            // Languages are preferred if they appear later, list is ordered by number of websites in that language
            // https://en.wikipedia.org/wiki/Languages_used_on_the_Internet#Content_languages_for_websites
            'armenian',
            'azerbaijani',
            'burmese',
            'hindi',
            'georgian',
            'norwegian',
            'vietnamese',
            'ukrainian',
            'latvian',
            'finnish',
            'greek',
            'czech',
            'arabic',
            'slovak',
            'turkish',
            'polish',
            'german',
            'russian',
            'romanian'
        ],
    ];

    private function __construct()
    {
        $this->provider = new DefaultRuleProvider();

        foreach ($this->options['rulesets'] as $ruleSet) {
            $this->activateRuleSet($ruleSet);
        }
    }

    static function getInstance(): self
    {
        if (self::$inst === null) {
            self::$inst = new self();
        }

        return self::$inst;
    }

    function slugify(string $string, array $options = []): string
    {
        $options += $this->options;

        // add a custom ruleset without touching the default rules
        if (isset($options['ruleset'])) {
            $rules = array_merge($this->rules, $this->provider->getRules($options['ruleset']));
        } else {
            $rules = $this->rules;
        }

        $string = strtr($string, $rules);
        unset($rules);

        if ($options['lowercase']) {
            $string = mb_strtolower($string);
        }

        $string = preg_replace($options['regexp'], $options['separator'], $string);

        return ($options['trim'])
            ? trim($string, $options['separator'])
            : $string;
    }

    function activateRuleSet(string $ruleSet): void
    {
        $this->rules += $this->provider->getRules($ruleSet);
    }
}
