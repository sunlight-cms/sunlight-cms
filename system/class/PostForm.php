<?php

namespace Sunlight;

abstract class PostForm
{
    /**
     * Sestavit kod ovladaciho panelu na smajliky a BBCode tagy
     *
     * @param string $form    nazev formulare
     * @param string $area    nazev textarey
     * @param bool   $bbcode  zobrazit BBCode 1/0
     * @param bool   $smileys zobrazit smajliky 1/0
     * @return string
     */
    static function renderControls($form, $area, $bbcode = true, $smileys = true)
    {
        $template = \Sunlight\Template::getCurrent();

        $output = '';

        // bbcode
        if ($bbcode && _bbcode && $template->getOption('bbcode.buttons')) {

            // nacteni tagu
            $bbtags = \Sunlight\Bbcode::parse(null, true);

            // pridani kodu
            $output .= '<span class="post-form-bbcode">';
            foreach ($bbtags as $tag => $vars) {
                if (!isset($vars[4])) {
                    // tag bez tlacitka
                    continue;
                }
                $icon = (($vars[4] === 1) ? \Sunlight\Template::image("bbcode/" . $tag . ".png") : $vars[4]);
                $output .= "<a class=\"bbcode-button post-form-bbcode-{$tag}\" href=\"#\" onclick=\"return Sunlight.addBBCode('" . $form . "','" . $area . "','" . $tag . "', " . ($vars[0] ? 'true' : 'false') . ");\" class='bbcode-button'><img src=\"" . $icon . "\" alt=\"" . $tag . "\"></a>\n";
            }
            $output .= '</span>';
        }

        // smajlici
        if ($smileys && _smileys) {
            $smiley_count = $template->getOption('smiley.count');
            $output .= '<span class="post-form-smileys">';
            for($x = 1; $x <= $smiley_count; ++$x) {
                $output .= "<a href=\"#\" onclick=\"return Sunlight.addSmiley('" . $form . "','" . $area . "'," . $x . ");\" class='smiley-button'><img src=\"" . $template->getWebPath() . '/images/smileys/' . $x . '.' . $template->getOption('smiley.format') . "\" alt=\"" . $x . "\" title=\"" . $x . "\"></a> ";
            }
            $output .= '</span>';
        }

        return "<span class='posts-form-buttons'>" . trim($output) . "</span>";
    }

    /**
     * Sestavit kod tlacitka pro nahled prispevku
     *
     * @param string $form nazev formulare
     * @param string $area nazev textarey
     * @return string
     */
    static function renderPreviewButton($form, $area)
    {
        return '<button class="post-form-preview" onclick="Sunlight.postPreview(this, \'' . $form . '\', \'' . $area . '\'); return false;">' . _lang('global.preview') . '</button>';
    }
}
