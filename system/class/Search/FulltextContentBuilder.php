<?php

namespace Sunlight\Search;

use Sunlight\Hcm;
use Sunlight\Settings;
use Sunlight\Util\Html;
use Sunlight\Util\StringHelper;

class FulltextContentBuilder
{
    /** @var string[] */
    private $parts = [];

    /**
     * @param array{remove_hcm?: bool, strip_tags?: bool, unescape_html?: bool} $options
     */
    function add(?string $part, array $options = []): void
    {
        if ($part === '' || $part === null) {
            return;
        }

        if ($options['remove_hcm'] ?? false) {
            $part = Hcm::remove($part);
        }

        if ($options['strip_tags'] ?? false) {
            $part = strip_tags($part);
        }

        if ($options['unescape_html'] ?? false) {
            $part = Html::unescape($part);
        }

        $part = StringHelper::trimExtraWhitespace($part);

        if ($part === '') {
            return;
        }

        $this->parts[] = $part;
    }

    function build(): string
    {
        return StringHelper::cut(implode(' ', $this->parts), (int) Settings::get('fulltext_content_limit'));
    }
}
