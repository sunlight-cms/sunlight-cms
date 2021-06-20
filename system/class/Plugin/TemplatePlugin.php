<?php

namespace Sunlight\Plugin;

use Sunlight\Database\Database as DB;
use Sunlight\Localization\LocalizationDictionary;
use Sunlight\Localization\LocalizationDirectory;

class TemplatePlugin extends Plugin
{
    const DEFAULT_LAYOUT = 'default';

    /** @var LocalizationDictionary */
    protected $lang;

    function __construct(PluginData $data, PluginManager $manager)
    {
        parent::__construct($data, $manager);

        $this->lang = new LocalizationDirectory($this->options['lang_dir']);
    }

    function canBeDisabled(): bool
    {
        return !$this->isDefault() && parent::canBeDisabled();
    }

    function canBeRemoved(): bool
    {
        return !$this->isDefault() && parent::canBeRemoved();
    }

    /**
     * See if this is the default template
     *
     * @return bool
     */
    function isDefault(): bool
    {
        return $this->id === _default_template;
    }

    /**
     * Notify the template plugin that it is going to be used to render a front end page
     *
     * @param string $layout
     */
    function begin(string $layout): void
    {
    }

    /**
     * Get the localization dictionary
     *
     * @return LocalizationDictionary
     */
    function getLang(): LocalizationDictionary
    {
        return $this->lang;
    }
    
    /**
     * Get template file path for the given layout
     *
     * @param string $layout
     * @return string
     */
    function getTemplate(string $layout = self::DEFAULT_LAYOUT): string
    {
        if (!isset($this->options['layouts'][$layout])) {
            $layout = self::DEFAULT_LAYOUT;
        }

        return $this->options['layouts'][$layout]['template'];
    }

    /**
     * See if the given layout exists
     *
     * @param string $layout layout name
     * @return bool
     */
    function hasLayout(string $layout): bool
    {
        return isset($this->options['layouts'][$layout]);
    }

    /**
     * Get list of template layout identifiers
     *
     * @return string[]
     */
    function getLayouts(): array
    {
        return array_keys($this->options['layouts']);
    }

    /**
     * Get label for the given layout
     *
     * @param string $layout layout name
     * @return string
     */
    function getLayoutLabel(string $layout): string
    {
        return $this->lang->get("{$layout}.label");
    }

    /**
     * See if the given slot exists
     *
     * @param string $layout
     * @param string $slot
     * @return bool
     */
    function hasSlot(string $layout, string $slot): bool
    {
        return in_array($slot, $this->getSlots($layout), true);
    }

    /**
     * Get list of slot identifiers for the given layout
     *
     * @param string $layout layout name
     * @return string[]
     */
    function getSlots(string $layout): array
    {
        if (isset($this->options['layouts'][$layout])) {
            return $this->options['layouts'][$layout]['slots'];

        }

        return [];
    }

    /**
     * Get label for the given layout and slot
     *
     * @param string $layout
     * @param string $slot
     * @return string
     */
    function getSlotLabel(string $layout, string $slot): string
    {
        return $this->lang->get("{$layout}.slot.{$slot}");
    }

    /**
     * Get boxes for the given layout
     *
     * @param string $layout
     * @return array
     */
    function getBoxes(string $layout = self::DEFAULT_LAYOUT): array
    {
        if (!isset($this->options['layouts'][$layout])) {
            $layout = self::DEFAULT_LAYOUT;
        }

        $boxes = [];
        $query = DB::query('SELECT id,title,content,slot,page_ids,page_children,class FROM ' . _box_table . ' WHERE template=' . DB::val($this->id) . ' AND layout=' . DB::val($layout) . ' AND visible=1' . (!_logged_in ? ' AND public=1' : '') . ' AND level <= ' . _priv_level . ' ORDER BY ord');

        while ($box = DB::row($query)) {
            $boxes[$box['slot']][$box['id']] = $box;
        }

        DB::free($query);

        return $boxes;
    }

    function getImagePath(string $name, bool $absolute = false): string
    {
        return $this->getWebPath($absolute) . "/images/{$name}";
    }

    /**
     * @return string
     */
    protected function getLocalizationPrefix(): string
    {
        return "{$this->type}_{$this->id}";
    }
}
