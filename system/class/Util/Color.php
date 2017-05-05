<?php

namespace Sunlight\Util;

class Color
{
    protected $r, $g, $b, $h, $l, $s;

    /**
     * @param array $color color segments
     * @param int   $type  color model (0 = rgb, 1 = hls)
     */
    public function __construct($color = array(0, 0, 0), $type = 0)
    {
        if ($type === 0) {
            list($this->r, $this->g, $this->b) = $color;
            list($this->h, $this->l, $this->s) = $this->rgbToHls($color[0], $color[1], $color[2]);
        } else {
            list($this->h, $this->l, $this->s) = $color;
            list($this->r, $this->g, $this->b) = $this->hlsToRgb($color[0], $color[1], $color[2]);
        }
    }

    /**
     * Set RGB channels
     *
     * @param int $r red channel
     * @param int $g green channel
     * @param int $b blue channel
     */
    public function setRGB($r, $g, $b)
    {
        list($this->h, $this->l, $this->s) = $this->rgbToHls($r, $g, $b);
        list($this->r, $this->g, $this->b) = func_get_args();
    }

    /**
     * Set HLS channels
     *
     * @param int $h hue
     * @param int $l lightness
     * @param int $s saturation
     */
    public function setHLS($h, $l, $s)
    {
        list($this->r, $this->g, $this->b) = $this->hlsToRgb($h, $l, $s);
        list($this->h, $this->l, $this->s) = func_get_args();
    }

    /**
     * Change color channel value
     *
     * @param string $channel channel name - r/g/b/h/l/s
     * @param int    $value   new value (0-255)
     * @return bool
     */
    public function setChannel($channel, $value)
    {
        // check arguments
        if (!isset($this->$channel)) {
            return false;
        }
        $value = (int) $value;

        // update channel
        $this->$channel = $value;
        if ($channel === 'r' || $channel === 'g' || $channel === 'b') {
            list($this->h, $this->l, $this->s) = $this->rgbToHls($this->r, $this->g, $this->b);
        } else {
            list($this->r, $this->g, $this->b) = $this->hlsToRgb($this->h, $this->l, $this->s);
        }
        return true;
    }

    /**
     * Get color channel value
     *
     * @param string $channel channel name - r/g/b/h/l/s
     * @return int|null 0-255 or null for unknown channel
     */
    public function getChannel($channel)
    {
        if (isset($this->$channel)) {
            return $this->$channel;
        }
    }

    /**
     * Get RGB channels
     *
     * @return array array(r,g,b)
     */
    public function getRGB()
    {
        return array($this->r, $this->g, $this->b);
    }

    /**
     * Get RGB channels as HTML string
     *
     * @return string #rrggbb
     */
    public function getRGBStr()
    {
        return sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b);
    }

    /**
     * Get HLS channels
     *
     * @return array array(h,l,s)
     */
    public function getHLS()
    {
        array($this->h, $this->l, $this->s);
    }

    /**
     * Get RGB values of HLS color
     *
     * @param int   $h hue (0-255)
     * @param float $l lightness (0-255)
     * @param float $s saturation (0-255)
     * @return array array(r,g,b)
     */
    protected function hlsToRgb($h, $l, $s)
    {
        // normalize args
        $args = array('h', 'l', 's');
        for($i = 0; $i < 3; ++$i) {
            if (${$args[$i]} < 0) {
                ${$args[$i]} = 0;
            } elseif (${$args[$i]} > 255) {
                ${$args[$i]} = 255;
            }
        }

        // convert
        $h = 360 * $h / 255;
        $l = ($l - 127) / 255;
        $s = $s / 255;
        $c = (1 - abs(2 * $l)) * $s;
        $hx = $h / 60;
        $x = $c * (1 - abs(fmod($hx, 2) - 1));
        if ($hx >= 0 && $hx < 1) {
            $rgb = array($c, $x, 0);
        } elseif ($hx >= 1 && $hx < 2) {
            $rgb = array($x, $c, 0);
        } elseif ($hx >= 2 && $hx < 3) {
            $rgb = array(0, $c, $x);
        } elseif ($hx >= 3 && $hx < 4) {
            $rgb = array(0, $x, $c);
        } elseif ($hx >= 4 && $hx < 5) {
            $rgb = array($x, 0, $c);
        } else {
            $rgb = array($c, 0, $x);
        }
        $m = $l - $c * .5;
        for($i = 0; $i < 3; ++$i) {
            $rgb[$i] = $this->range(floor(($rgb[$i] + $m) * 255 + 127), 0, 255);
        }

        return $rgb;
    }

    /**
     * Get HLS values of RGB color
     *
     * @param int $r red channel (0-255)
     * @param int $g green channel (0-255)
     * @param int $b blue channel (0-255)
     * @return array array(h,l,s)
     */
    protected function rgbToHls($r, $g, $b)
    {
        // normalize args
        $args = array('r', 'g', 'b');
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
            return array(0, $l, 0);
        }
        if ($M === $r) {
            $hx = fmod(($g - $b) / $c, 6);
        } elseif ($M === $g) {
            $hx = ($b - $r) / $c + 2;
        } else {
            $hx = ($r - $g) / $c + 4;
        }
        $h = round($hx * 60 / 360 * 255);
        $s = round($c / (1 - abs(2 * ($l - 127) / 255)));
        $l = round($l);

        return array($h, $l, $s);
    }

    /**
     * Limit number range
     *
     * @param number      $num the number
     * @param number|null $min minimal value or null (= unlimited)
     * @param number|null $max maximal value or null (= unlimited)
     * @return number
     */
    protected function range($num, $min, $max)
    {
        if (isset($min) && $num < $min) {
            return $min;
        }

        if (isset($max) && $num > $max) {
            return $max;
        }

        return $num;
    }
}
