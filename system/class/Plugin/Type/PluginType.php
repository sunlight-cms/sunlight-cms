<?php

namespace Sunlight\Plugin\Type;

use Kuria\Options\Exception\ResolverException;
use Kuria\Options\Node;
use Kuria\Options\Option;
use Kuria\Options\Resolver;
use Sunlight\Plugin\PluginData;
use Sunlight\Plugin\PluginOptionNormalizer;

abstract class PluginType
{
    /** @var Resolver|null */
    private $optionResolver;

    /** @var Resolver|null */
    private $fallbackOptionResolver;

    abstract function getName(): string;

    abstract function getDir(): string;

    abstract function getClass(): string;

    abstract function getDefaultBaseNamespace(): string;

    final function resolveOptions(PluginData $plugin, array $options): void
    {
        $optionResolver = $this->getOptionResolver();

        try {
            $plugin->options = $optionResolver->resolve($options, [$plugin])->toArray();
        } catch (ResolverException $e) {
            $plugin->addError(...$e->getErrors());
        }
    }

    final function resolveFallbackOptions(PluginData $plugin): void
    {
        $fallbackOptions = [
            'name' => $plugin->id,
            'version' => '0.0.0',
            'api' => '0.0.0',
        ];

        $plugin->options = $this->getFallbackOptionResolver()->resolve($fallbackOptions, [$plugin])->toArray();
    }

    /**
     * @return Option\OptionDefinition[]
     */
    private function getBaseOptions(): array
    {
        return [
            Option::any('$schema')->default(null),
            Option::string('name'),
            Option::string('description')->default(null),
            Option::string('author')->default(null),
            Option::string('url')->default(null),
            Option::string('version'),
            Option::string('api'),
            Option::string('php')->default(null),
            Option::list('extensions', 'string')->default([]),
            Option::list('requires', 'string')->default([]),
            Option::string('installer')
                ->normalize([PluginOptionNormalizer::class, 'normalizePath'])
                ->default(null),
            Option::node(
                'autoload',
                Option::array('psr-0')->default([]),
                Option::array('psr-4')->default([]),
                Option::list('classmap', 'string')->default([])
            )
                ->normalize([PluginOptionNormalizer::class, 'normalizeAutoload']),
            Option::bool('debug')->default(null),
            Option::string('class')->default(null),
            Option::string('namespace')->default(null),
            Option::bool('inject_composer')->default(true),
            Option::list('extra', null)->default([]),
        ];
    }

    private function getOptionResolver(): Resolver
    {
        if ($this->optionResolver === null) {
            $this->optionResolver = new Resolver();
            $this->configureOptionResolver($this->optionResolver);
        }

        return $this->optionResolver;
    }

    private function getFallbackOptionResolver(): Resolver
    {
        if ($this->fallbackOptionResolver === null) {
            $this->fallbackOptionResolver = new Resolver();
            $this->fallbackOptionResolver->addOption(...$this->getBaseOptions());
        }

        return $this->fallbackOptionResolver;
    }

    protected function configureOptionResolver(Resolver $optionResolver): void
    {
        $optionResolver->addOption(...$this->getBaseOptions());

        $optionResolver->addNormalizer(function (Node $node, PluginData $plugin) {
            if ($node['namespace'] === null) {
                $node['namespace'] = $this->getDefaultBaseNamespace() . '\\' . $plugin->camelId;
            }

            return $node;
        });
    }
}
