<?php

namespace Sunlight;

abstract class PostForm
{
    /**
     * Render post form controls
     *
     * @param string $form form name
     * @param string $area textarea name
     * @param bool $bbcode enable BBCode buttons 1/0
     */
    static function renderControls(string $form, string $area, bool $bbcode = true): string
    {
        $output = '';

        Extend::call('post_form.controls.before', [
            'form' => $form,
            'area' => $area,
            'bbcode' => &$bbcode,
            'output' => &$output,
        ]);

        // bbcode
        if ($bbcode && Settings::get('bbcode') && Template::getCurrent()->getOption('bbcode.buttons')) {
            // load tags
            $bbtags = Bbcode::getTags();

            // render buttons
            $output .= '<span class="post-form-bbcode">';

            foreach ($bbtags as $tag => $vars) {
                if (!isset($vars[4])) {
                    // button-less tag
                    continue;
                }

                $icon = (($vars[4] === 1) ? Template::image('bbcode/' . $tag . '.png') : $vars[4]);
                $output .= '<a class="bbcode-button post-form-bbcode-' . $tag . '" href="#" onclick="return Sunlight.addBBCode(\'' . $form . '\',\'' . $area . '\',\'' . $tag . '\', ' . ($vars[0] ? 'true' : 'false') . ');" class="bbcode-button"><img src="' . $icon . '" alt="' . $tag . "\"></a>\n";
            }

            $output .= '</span>';
        }

        Extend::call('post_form.controls.after', [
            'form' => $form,
            'area' => $area,
            'bbcode' => $bbcode,
            'output' => &$output,
        ]);

        if ($output !== '') {
            $output = '<span class="posts-form-controls">' . $output . '</span>';
        }

        return $output;
    }

    /**
     * Render post preview button
     *
     * @param string $form form name
     * @param string $area textarea name
     */
    static function renderPreviewButton(string $form, string $area): string
    {
        return '<button class="post-form-preview" onclick="Sunlight.postPreview(this, \'' . $form . '\', \'' . $area . '\'); return false;">' . _lang('global.preview') . '</button>';
    }
}
