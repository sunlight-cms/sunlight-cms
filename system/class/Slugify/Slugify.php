<?php

namespace Sunlight\Slugify;

class Slugify
{
    const LOWERCASE_NUMBERS_DASHES = '/([^A-Za-z0-9]|-)+/';

    /** @var self|null */
    private static $inst;

    /** @var string[] */
    protected $rules = array();

    /** @var RuleProviderInterface */
    protected $provider;

    /** @var array */
    protected $options = array(
        'regexp' => self::LOWERCASE_NUMBERS_DASHES,
        'separator' => '-',
        'lowercase' => true,
        'trim' => true,
        'rulesets' => array(
            'default',
            // Languages are preferred if they appear later, list is ordered by number of
            // websites in that language
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
        ),
    );

    protected function __construct(array $options = array(), RuleProviderInterface $provider = null)
    {
        $this->options = array_merge($this->options, $options);
        $this->provider = $provider ? $provider : new DefaultRuleProvider();

        foreach ($this->options['rulesets'] as $ruleSet) {
            $this->activateRuleSet($ruleSet);
        }
    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (self::$inst === null) {
            self::$inst = new self();
        }

        return self::$inst;
    }

    /**
     * @param string $string
     * @param string|array|null $options
     * @return string
     */
    function slugify($string, $options = null)
    {
        $options = array_merge($this->options, (array) $options);

        // Add a custom ruleset without touching the default rules
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

    /**
     * @param string $character character
     * @param string $replacement replacement character
     */
    function addRule($character, $replacement)
    {
        $this->rules[$character] = $replacement;
    }

    /**
     * @param string[] $rules
     */
    function addRules(array $rules)
    {
        foreach ($rules as $character => $replacement) {
            $this->addRule($character, $replacement);
        }
    }

    /**
     * @param string $ruleSet
     */
    function activateRuleSet($ruleSet)
    {
        $this->addRules($this->provider->getRules($ruleSet));
    }
}
