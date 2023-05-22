<?php

namespace Sunlight\Plugin\Type;

use Kuria\Options\Exception\ResolverException;
use Kuria\Options\Node;
use Kuria\Options\Option;
use Kuria\Options\Resolver;
use Sunlight\CallbackHandler;
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
            'name' => $plugin->name,
            'version' => '0.0.0',
            'environment' => [
                'system' => '0.0.0',
            ],
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
            Option::nodeList(
                'authors',
                Option::string('name')->default(function (Node $node) {
                    return $node['url'] !== null
                        ? parse_url($node['url'], PHP_URL_HOST)
                        : null;
                }),
                Option::string('url')->default(null)
            )->validate(function (array $authors) {
                foreach ($authors as $key => $author) {
                    if ($author['name'] === null && $author['url'] === null) {
                        return sprintf('[%s] must specify at least name or url, got none', $key);
                    }
                }
            }),
            Option::string('version'),
            Option::node(
                'environment',
                Option::string('system'),
                Option::string('php')->default(null),
                Option::list('php_extensions', 'string')->default([]),
                Option::bool('debug')->default(null)
            ),
            Option::list('dependencies', 'string')
                ->normalize([PluginOptionNormalizer::class, 'normalizeDependencies'])
                ->default([]),
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
            Option::string('class')
                ->default($this->getClass()),
            Option::string('namespace')
                ->default(function (Node $node, PluginData $plugin) {
                    return $this->getDefaultBaseNamespace() . '\\' . $plugin->camelCasedName;
                }),
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

        $optionResolver->addNormalizer(function (Node $node) {
            $node['class'] = PluginOptionNormalizer::normalizeClass($node['class'], $node['namespace']);

            return $node;
        });
    }

    protected function createEventSubscribersOption(string $name, Option\OptionDefinition ...$extraOptions): Option\NodeOption
    {
        return Option::nodeList(
            $name,
            Option::string('event'),
            Option::int('priority')->default(0),
            ...$extraOptions,
            ...CallbackHandler::getDefinitionOptions()
        )->normalize([PluginOptionNormalizer::class, 'normalizeCallbackNodes']);
    }
}
