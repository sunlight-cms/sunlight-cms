<?php

namespace Sunlight\Util;

class Color
{
    protected $r, $g, $b, $h, $s, $l;

    /**
     * @param array $color color segments
     * @param int   $type  color model (0 = rgb, 1 = hsl)
     */
    function __construct(array $color = [0, 0, 0], int $type = 0)
    {
        if ($type === 0) {
            [$this->r, $this->g, $this->b] = $color;
            [$this->h, $this->s, $this->l] = $this->rgbToHsl($color[0], $color[1], $color[2]);
        } else {
            [$this->h, $this->s, $this->l] = $color;
            [$this->r, $this->g, $this->b] = $this->hslToRgb($color[0], $color[1], $color[2]);
        }
    }

    /**
     * Create color from a RGB HEX string.
     *
     * Supported formats are #xxxxxx or #xxx (shorthand).
     *
     * @param $color
     * @return self|null
     */
    static function fromString($color): ?self
    {
        if (preg_match('{#([0-9a-f]{3,6})$}ADi', $color, $match)) {
            return new self(
                strlen($match[1]) === 3
                    ? array_map(function ($hexit) { return hexdec($hexit . $hexit); }, str_split($match[1]))
                    : array_map('hexdec', str_split($match[1], 2))
            );
        }

        return null;
    }

    /**
     * Get the color as a RGB HEX string
     *
     * @return string
     */
    function __toString(): string
    {
        return $this->getRgbStr();
    }

    /**
     * Set RGB channels
     *
     * @param int $r red channel
     * @param int $g green channel
     * @param int $b blue channel
     */
    function setRgb(int $r, int $g, int $b): void
    {
        [$this->h, $this->s, $this->l] = $this->rgbToHsl($r, $g, $b);
        [$this->r, $this->g, $this->b] = func_get_args();
    }

    /**
     * Set HSL channels
     *
     * @param int $h hue
     * @param int $s saturation
     * @param int $l lightness
     */
    function setHsl(int $h, int $s, int $l): void
    {
        [$this->r, $this->g, $this->b] = $this->hslToRgb($h, $s, $l);
        [$this->h, $this->s, $this->l] = func_get_args();
    }

    /**
     * Change color channel value
     *
     * @param string $channel channel name - r/g/b/h/s/l
     * @param int    $value   new value (0-255)
     * @return bool
     */
    function setChannel(string $channel, int $value): bool
    {
        // check arguments
        if (!isset($this->$channel)) {
            return false;
        }

        // update channel
        $this->$channel = $value;
        if ($channel === 'r' || $channel === 'g' || $channel === 'b') {
            [$this->h, $this->s, $this->l] = $this->rgbToHsl($this->r, $this->g, $this->b);
        } else {
            [$this->r, $this->g, $this->b] = $this->hslToRgb($this->h, $this->s, $this->l);
        }
        return true;
    }

    /**
     * Get color channel value
     *
     * @param string $channel channel name - r/g/b/h/s/l
     * @return int|null 0-255 or null for unknown channel
     */
    function getChannel(string $channel): ?int
    {
        return $this->$channel ?? null;
    }

    /**
     * Get RGB channels
     *
     * @return array array(r,g,b)
     */
    function getRgb(): array
    {
        return [$this->r, $this->g, $this->b];
    }

    /**
     * Get RGB channels as HTML string
     *
     * @return string #rrggbb
     */
    function getRgbStr(): string
    {
        return sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b);
    }

    /**
     * Get HSL channels
     *
     * @return array array(h,s,l)
     */
    function getHsl(): array
    {
        return [$this->h, $this->s, $this->l];
    }

    /**
     * Get RGB values of HSL color
     *
     * @param int   $h hue (0-255)
     * @param float $s saturation (0-255)
     * @param float $l lightness (0-255)
     * @return array array(r,g,b)
     */
    private function hslToRgb(int $h, float $s, float $l): array
    {
        // normalize args
        $args = ['h', 's', 'l'];
        for($i = 0; $i < 3; ++$i) {
            if (${$args[$i]} < 0) {
                ${$args[$i]} = 0;
            } elseif (${$args[$i]} > 255) {
                ${$args[$i]} = 255;
            }
        }

        // convert
        $h = 360 * $h / 255;
        $s /= 255;
        $l = ($l - 127) / 255;
        $c = (1 - abs(2 * $l)) * $s;
        $hx = $h / 60;
        $x = $c * (1 - abs(fmod($hx, 2) - 1));
        if ($hx >= 0 && $hx < 1) {
            $rgb = [$c, $x, 0];
        } elseif ($hx >= 1 && $hx < 2) {
            $rgb = [$x, $c, 0];
        } elseif ($hx >= 2 && $hx < 3) {
            $rgb = [0, $c, $x];
        } elseif ($hx >= 3 && $hx < 4) {
            $rgb = [0, $x, $c];
        } elseif ($hx >= 4 && $hx < 5) {
            $rgb = [$x, 0, $c];
        } else {
            $rgb = [$c, 0, $x];
        }
        $m = $l - $c * .5;
        for($i = 0; $i < 3; ++$i) {
            $rgb[$i] = (int) Math::range(floor(($rgb[$i] + $m) * 255 + 127), 0, 255);
        }

        return $rgb;
    }

    /**
     * Get HSL values of RGB color
     *
     * @param int $r red channel (0-255)
     * @param int $g green channel (0-255)
     * @param int $b blue channel (0-255)
     * @return array array(h,s,l)
     */
    private function rgbToHsl(int $r, int $g, int $b): array
    {
        // normalize args
        $args = ['r', 'g', 'b'];
        for($i = 0; $i < 3; ++$i) {
            if (${$args[$i]} < 0) {
                ${$args[$i]} = 0;
            } elseif (${$args[$i]} > 255) {
                ${$args[$i]} = 255;
            }
        }

        // convert
        $M = max($r, $g, $b);
        $m = min($r, $g, $b);
        $l = .5 * ($M + $m);
        $c = $M - $m;
        if ($c === 0) {
            return [0, (int) $l, 0];
        }
        if ($M === $r) {
            $hx = fmod(($g - $b) / $c, 6);
        } elseif ($M === $g) {
            $hx = ($b - $r) / $c + 2;
        } else {
            $hx = ($r - $g) / $c + 4;
        }
        $h = (int) round($hx * 60 / 360 * 255);
        $l = (int) round($l);
        $s = (int) round($c / (1 - abs(2 * ($l - 127) / 255)));

        return [$h, $s, $l];
    }
}
