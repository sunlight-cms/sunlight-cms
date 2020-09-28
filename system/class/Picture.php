<?php

namespace Sunlight;

use Sunlight\Util\Color;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;

class Picture
{
    /**
     * Nacteni obrazku ze souboru
     *
     * Mozne klice v $limit:
     *
     * filesize     maximalni velikost souboru v bajtech
     * dimensions   max. rozmery ve formatu array(x => max_sirka, y => max_vyska)
     * memory       maximalni procento zbyvajici dostupne pameti, ktere muze byt vyuzito (vychozi je 0.75) a je treba pocitat s +- odchylkou
     *
     * @param string      $filepath realna cesta k souboru
     * @param array       $limit    volby omezeni
     * @param string|null $filename pouzity nazev souboru (pokud se lisi od $filepath)
     * @return array pole s klici (bool)status, (int)code, (string)msg, (resource)resource, (string)ext
     */
    static function load($filepath, $limit = array(), $filename = null)
    {
        // vychozi nastaveni
        static $limit_default = array(
            'filesize' => null,
            'dimensions' => null,
            'memory' => 0.75,
        );

        // vlozeni vychoziho nastaveni
        $limit += $limit_default;

        // proces
        $code = 0;
        do {

            /* --------  kontroly a nacteni  -------- */

            // zjisteni nazvu souboru
            if ($filename === null) {
                $filename = basename($filepath);
            }

            // zjisteni pripony
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // kontrola pripony
            if (!in_array($ext, Core::$imageExt) || !Filesystem::isSafeFile($filepath) || !Filesystem::isSafeFile($filename)) {
                // nepovolena pripona
                $code = 1;
                break;
            }

            // kontrola velikosti souboru
            $size = @filesize($filepath);
            if ($size === false) {
                // soubor nenalezen
                $code = 2;
                break;
            }
            if (isset($limit['filesize']) && $size > $limit['filesize']) {
                // prekrocena datova velikost
                $code = 3;
                break;
            }

            // kontrola podpory formatu
            if (!static::checkFormatSupport($ext)) {
                // nepodporovany format
                $code = 4;
                break;
            }

            // zjisteni informaci o obrazku
            $imageInfo = getimagesize($filepath);
            if (isset($imageInfo['channels'])) {
                $channels = $imageInfo['channels'];
            } else {
                switch ($ext) {
                    case 'png': $channels = 4; break;
                    default: $channels = 3; break;
                }
            }
            if (!isset($imageInfo['bits'])) {
                $imageInfo['bits'] = 8;
            }
            if ($imageInfo === false || $imageInfo[0] == 0 || $imageInfo[1] == 0) {
                $code = 5;
                break;
            }

            // kontrola dostupne pameti
            $availMem = Environment::getAvailableMemory();
            $requiredMem = ceil(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $channels / 8 + 65536) * 1.65);

            if ($availMem !== null && $requiredMem > $limit['memory'] * $availMem) {
                // nedostatek pameti
                $code = 5;
                break;
            }

            // nacteni rozmeru
            $x = $imageInfo[0];
            $y = $imageInfo[1];

            // kontrola rozmeru
            if (isset($limit['dimensions']) && ($x > $limit['dimensions']['x'] || $y > $limit['dimensions']['y'])) {
                $code = 6;
                break;
            }

            // pokus o nacteni obrazku
            $res = null;

            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $res = @imagecreatefromjpeg($filepath);
                    break;

                case 'png':
                    $res = @imagecreatefrompng($filepath);
                    break;

                case 'gif':
                    $res = @imagecreatefromgif ($filepath);
                    break;
            }

            // kontrola nacteni
            if (!is_resource($res)) {
                $code = 5;
                break;
            }

            // vsechno je ok, vratit vysledek
            return array('status' => true, 'code' => $code, 'resource' => $res, 'ext' => $ext);

        } while (false);

        // chyba
        $output = array('status' => false, 'code' => $code, 'msg' => _lang('pic.load.' . $code), 'ext' => $ext);

        // uprava vystupu
        switch ($code) {
            case 3:
                $output['msg'] = str_replace('*maxsize*', GenericTemplates::renderFileSize($limit['filesize']), $output['msg']);
                break;

            case 5:
                $lastError = error_get_last();
                if ($lastError !== null && !empty($lastError['message'])) {
                    $output['msg'] .= ' ' . _lang('global.error') . ': ' . _e($lastError['message']);
                }
                break;

            case 6:
                $output['msg'] = str_replace(array('*maxw*', '*maxh*'), array($limit['dimensions']['x'], $limit['dimensions']['y']), $output['msg']);
                break;
        }

        // navrat
        return $output;
    }

    /**
     * Zmena velikosti obrazku
     *
     * Mozne klice v $opt:
     * -----------------------------------------------------
     * x (-)            pozadovana sirka obrazku (nepovinne pokud je uvedeno y)
     * y (-)            pozadovana vyska obrazku (nepovinne pokud je uvedeno x)
     * mode (-)         mod zpracovani - 'zoom', 'fit' nebo 'none' (zadna operace)
     * keep_smaller (0) zachovat mensi obrazky 1/0
     * bgcolor (-)      barva pozadi ve formatu array(r, g, b) (pouze mod 'fit')
     * pad (0)          doplnit rozmer obrazku prazdnym mistem 1/0 (pouze mod 'fit')
     *
     * trans            zachovat pruhlednost obrazku (ignoruje klic bgcolor) 1/0
     * trans_format     format obrazku (png/gif), vyzadovano pro trans = 1
     *
     * @param resource   $res  resource obrazku
     * @param array      $opt  pole s volbami procesu
     * @param array|null $size pole ve formatu array(sirka, vyska) nebo null (= bude nacteno)
     * @return array pole s klici (bool)status, (int)code, (string)msg, (resource)resource, (bool)changed
     */
    static function resize($res, array $opt, $size = null)
    {
        // vychozi nastaveni
        $opt += array(
            'x' => null,
            'y' => null,
            'mode' => null,
            'keep_smaller' => false,
            'bgcolor' => null,
            'pad' => false,
            'trans' => false,
            'trans_format' => null,
        );

        $extend_output = null;

        Extend::call('picture.resize', array(
            'res' => &$res,
            'options' => &$opt,
            'output' => &$extend_output,
        ));

        if ($extend_output !== null) {
            return $extend_output;
        }

        // zadna operace?
        if ($opt['mode'] === 'none') {
            return array('status' => true, 'code' => 0, 'resource' => $res, 'changed' => false);
        }

        // zjisteni rozmeru
        if (!isset($size)) {
            $x = imagesx($res);
            $y = imagesy($res);
        } else {
            list($x, $y) = $size;
        }

        // rozmery kompatibilita 0 => null
        if ($opt['x'] == 0) {
            $opt['x'] = null;
        }
        if ($opt['y'] == 0) {
            $opt['y'] = null;
        }

        // kontrola parametru
        if ($opt['x'] === null && $opt['y'] === null || $opt['y'] !== null && $opt['y'] < 1 || $opt['x'] !== null && $opt['x'] < 1) {
            return array('status' => false, 'code' => 2, 'msg' => _lang('pic.resize.2'));
        }

        // proporcionalni dopocet chybejiciho rozmeru
        if ($opt['x'] === null) {
            $opt['x'] = max(round($x / $y * $opt['y']), 1);
        } elseif ($opt['y'] === null) {
            $opt['y'] = max(round($opt['x'] / ($x / $y)), 1);
        }

        // povolit mensi rozmer / stejny rozmer
        if (
            $opt['keep_smaller'] && $x < $opt['x'] && $y < $opt['y']
            || $x == $opt['x'] && $y == $opt['y']
        ) {
            return array('status' => true, 'code' => 0, 'resource' => $res, 'changed' => false);
        }

        // vypocet novych rozmeru
        $newx = $opt['x'];
        $newy = max(round($opt['x'] / ($x / $y)), 1);

        // volba finalnich rozmeru
        $xoff = $yoff = 0;
        if ($opt['mode'] === 'zoom') {
            if ($newy < $opt['y']) {
                $newx = max(round($x / $y * $opt['y']), 1);
                $newy = $opt['y'];
                $xoff = round(($opt['x'] - $newx) / 2);
            } elseif ($newy > $opt['y']) {
                $yoff = round(($opt['y'] - $newy) / 2);
            }
        } elseif ($opt['mode'] === 'fit') {
            if ($newy < $opt['y']) {
                if ($opt['pad']) {
                    $yoff = round(($opt['y'] - $newy) / 2);
                } else {
                    $opt['y'] = $newy;
                }
            } elseif ($newy > $opt['y']) {
                $newy = $opt['y'];
                $newx = max(round($x / $y * $opt['y']), 1);
                if ($opt['pad']) {
                    $xoff = round(($opt['x'] - $newx) / 2);
                } else {
                    $opt['x'] = $newx;
                }
            }
        } else {
            return array('status' => false, 'code' => 1, 'msg' => _lang('pic.resize.1'));
        }

        // priprava obrazku
        $output = imagecreatetruecolor($opt['x'], $opt['y']);

        // prekresleni pozadi
        if ($opt['trans'] && $opt['trans_format'] !== null) {
            // pruhledne
            static::enableAlpha($output, $opt['trans_format'], $res);
        } else {
            // nepruhledne
            if ($opt['mode'] === 'fit' && $opt['bgcolor'] !== null) {
                $bgc = imagecolorallocate($output, $opt['bgcolor'][0], $opt['bgcolor'][1], $opt['bgcolor'][2]);
                imagefilledrectangle($output, 0, 0, $opt['x'], $opt['y'], $bgc);
            }
        }

        // zmena rozmeru a navrat
        if (imagecopyresampled($output, $res, $xoff, $yoff, 0, 0, $newx, $newy, $x, $y)) {
            return array('status' => true, 'code' => 0, 'resource' => $output, 'changed' => true);
        }
        imagedestroy($output);

        return array('status' => false, 'code' => 2, 'msg' => _lang('pic.resize.2'));
    }

    /**
     * Aktivovat pruhlednost obrazku
     *
     * @param resource      $resource    resource obrazku
     * @param string        $format      vystupni format obrazku (png / gif)
     * @param resource|null $colorSource resource obrazku jako zdroj transp. barvy (jinak $resource)
     * @return bool
     */
    static function enableAlpha($resource, $format, $colorSource = null)
    {
        // paleta?
        $trans = imagecolortransparent($colorSource !== null ? $colorSource : $resource);
        if ($trans >= 0) {
            $transColor = imagecolorsforindex($resource, $trans);
            $transColorAl = imagecolorallocate($resource, $transColor['red'], $transColor['green'], $transColor['blue']);
            imagefill($resource, 0, 0, $transColorAl);
            imagecolortransparent($resource, $transColorAl);

            return true;
        }

        // png alpha?
        if ($format === 'png') {
            imagealphablending($resource, false);
            $transColorAl = imagecolorallocatealpha($resource, 0, 0, 0, 127);
            imagefill($resource, 0, 0, $transColorAl);
            imagesavealpha($resource, true);

            return true;
        }

        return false;
    }

    /**
     * Rozebrat definici rozmeru pro zmenu velikosti obrazku
     *
     * Vstupni retezec je seznam parametru oddelenych lomitkem.
     *
     * Podporovane casti:
     *
     *      NxN         sirka a vyska obrazku (jeden rozmer muze byt "?" pro automaticky prepocet)
     *      zoom        zoom rezim
     *      fit         fit rezim
     *      keep        zachovat mensi obrazky
     *      solid       nezachovavat pruhlednost obrazku
     *      pad         vyplnit zbyvajici misto barvou (pouze v rezimu fit)
     *      #xxxxxx     barva pozadi (pouze v rezimu fit + pad)
     *      #xxx        barva pozadi (zkracena verze; pouze v rezimu fit + pad)
     *
     * Priklady:
     *
     *      128x96
     *      128x?
     *      640x480/zoom
     *      320x?/fit/pad/#fff
     *
     * @param string   $input         vstupni retezec
     * @param string   $defaultMode   vychozi "mode" pro {@see Picture::resize()}
     * @param int|null $defaultWidth  vychozi sirka
     * @param int|null $defaultHeight vychozi vyska
     * @return array pole pro {@see Picture::resize()}
     */
    static function parseResizeOptions($input, $defaultMode = 'fit', $defaultWidth = 96, $defaultHeight = null)
    {
        $mode = $defaultMode;
        $pad = false;
        $bgColor = null;
        $keepSmaller = false;
        $width = null;
        $height = null;
        $trans = true;

        foreach (explode('/', $input) as $part) {
            switch ($part) {
                case 'zoom':
                    $mode = 'zoom';
                    break;

                case 'fit':
                    $mode = 'fit';
                    break;

                case 'keep':
                    $keepSmaller = true;
                    break;

                case 'solid':
                    $trans = false;
                    break;

                case 'pad':
                    $pad = true;
                    break;

                default:
                    if ($part === '') {
                        break;
                    }

                    if ($part[0] === '#') {
                        // bg color
                        $bgColor = Color::fromString($part);

                        if ($bgColor !== null) {
                            $bgColor = $bgColor->getRgb();
                        }
                    }

                    if (preg_match('{(\d++|\?)x(\d++|\?)$}AD', $part, $match)) {
                        // size
                        $width = $match[1] === '?' ? null : (int) $match[1];
                        $height = $match[2] === '?' ? null : (int) $match[2];
                    }
                    break;
            }
        }

        if ($width === null && $height === null) {
            $width = $defaultWidth;
            $height = $defaultHeight;
        }

        return array(
            'x' => $width,
            'y' => $height,
            'mode' => $mode,
            'pad' => $pad,
            'bgcolor' => $bgColor,
            'keep_smaller' => $keepSmaller,
            'trans' => $trans,
        );
    }

    /**
     * Ulozit obrazek do uloziste
     *
     * @param resource $res         resource obrazku
     * @param string   $target_path kompletni cesta k obrazku {@see Picture::get()}
     * @param string   $format      pozadovany format obrazku
     * @param int      $jpg_quality kvalita JPG obrazku
     * @return array
     */
    static function store($res, $target_path, $format, $jpg_quality)
    {
        // proces
        $code = 0;
        do {
            // kontrola formatu
            if (!static::checkFormatSupport($format)) {
                $code = 1;
                break;
            }

            // zapsani souboru
            $result = static::write($res, $format, $target_path, $jpg_quality);

            // uspech?
            if ($result) {
                return array('status' => true, 'code' => $code, 'path' => $target_path);
            }
            $code = 2;
        } while (false);

        // chyba
        return array('status' => false, 'code' => $code, 'msg' => _lang('pic.put.' . $code));
    }

    /**
     * Ziskat uplnou cestu k obrazku v ulozisti
     *
     * @param string $dir         cesta k adresari uloziste (relativne k _root) vcetne lomitka na konci
     * @param string $uid         UID obrazku
     * @param string $format      format ulozeneho obrazku
     * @param int    $partitions  pocet 2-znakovych subadresaru z $uid
     * @param bool   $root_prefix vratit cestu vcetne _root prefixu
     * @return string
     */
    static function get($dir, $uid, $format, $partitions = 0, $root_prefix = true)
    {
        Extend::call('picture.get', array(
            'dir' => &$dir,
            'uid' => $uid,
            'format' => &$format,
            'partitions' => &$partitions,
        ));

        $full_path = '';

        if ($root_prefix) {
            $full_path .= _root;
        }

        $full_path .= $dir;

        for ($i = 0; $i < $partitions; ++$i) {
            $full_path .= mb_substr($uid, $i * 2, 2) . '/';
        }

        $full_path .= $uid . '.' . $format;

        return $full_path;
    }

    /**
     * Zpracovat obrazek
     *
     * Navratova hodnota
     * -----------------
     * false        je vraceno v pripade neuspechu
     * string       UID je vraceno v pripade uspesneho ulozeni
     * resource     je vraceno v pripade uspesneho zpracovani bez ulozeni (target_dir je null)
     * mixed        pokud je uveden target_callback a vrati jinou hodnotu nez null
     *
     * Dostupne klice v $args :
     * -----------------------------------------------------
     * Nacteni a zpracovani
     *
     *  file_path       realna cesta k souboru s obrazkem
     *  [file_name]     vlastni nazev souboru pro detekci formatu (jinak se pouzije file_path)
     *  [limit]         omezeni pri nacitani obrazku, viz _pictureLoad() - $limit
     *  [resize]        pole s argumenty pro zmenu velikosti obrazku, viz _pictureResize()
     *  [callback]      callback(resource, format, opt) pro zpracovani vysledne resource
     *                  (pokud vrati jinou hodnotu nez null, obrazek nebude ulozen a funkce
     *                   vrati tuto hodnotu)
     *  [destroy]       pokud je nastaveno na false, neni resource obrazku znicena (po ulozeni / volani callbacku)
     *
     * Ukladani
     *
     *  [target_dir]        cesta k adresari uloziste (relativne k _root) vcetne lomitka na konci (null = neukladat)
     *  [target_format]     cilovy format (jpg, jpeg, png, gif), pokud neni uveden, je zachovan stavajici format
     *  [target_uid]        vlastni unikatni identifikator, jinak bude vygenerovan automaticky
     *  [target_partitions] pocet 2-znakovych subadresaru z $uid
     *  [jpg_quality]       kvalita pro ukladani JPG/JPEG formatu
     *
     * @param array         $opt      volby zpracovani
     * @param string        &$error   promenna pro ulozeni chybove hlasky v pripade neuspechu
     * @param string        &$format  promenna pro ulozeni formatu nacteneho obrazku
     * @param resource|null $resource promenna pro ulozeni resource vysledneho obrazku (pouze pokud 'destroy' = false)
     * @return mixed viz popis funkce
     */
    static function process(array $opt, &$error = null, &$format = null, &$resource = null)
    {
        $opt += array(
            'file_name' => null,
            'limit' => array(),
            'resize' => null,
            'callback' => null,
            'destroy' =>  true,
            'target_dir' => null,
            'target_format' => null,
            'target_uid' => null,
            'target_partitions' => 0,
            'jpg_quality' => 90,
        );

        Extend::call('picture.process', array('options' => &$opt));

        try {
            // nacteni
            $load = static::load(
                $opt['file_path'],
                $opt['limit'],
                $opt['file_name']
            );
            if (!$load['status']) {
                throw new \RuntimeException($load['msg']);
            }
            $format = $load['ext'];

            // zmena velikosti
            if ($opt['resize'] !== null) {
                // zachovat pruhlednost, neni-li uvedeno jinak
                if (
                    !isset($opt['resize']['trans'])
                    && ($format === 'png' || $format === 'gif')
                    && ($opt['target_format'] === null || $opt['target_format'] === $format)
                ) {
                    $opt['resize']['trans'] = true;
                    $opt['resize']['trans_format'] = $format;
                }

                // zmenit velikost
                $resize = static::resize($load['resource'], $opt['resize']);
                if (!$resize['status']) {
                    throw new \RuntimeException($resize['msg']);
                }

                // nahrada puvodni resource
                if ($resize['changed']) {
                    // resource se zmenila
                    imagedestroy($load['resource']);
                    $load['resource'] = $resize['resource'];
                }

                $resize = null;
            }

            // callback
            if ($opt['callback'] !== null) {
                $targetCallbackResult = call_user_func($opt['callback'], $load['resource'], $load['ext'], $opt);
                if ($targetCallbackResult !== null) {
                    // smazani obrazku z pameti
                    if ($opt['destroy']) {
                        imagedestroy($load['resource']);
                        $resource = null;
                    } else {
                        $resource = $load['resource'];
                    }

                    // navrat vystupu callbacku
                    return $targetCallbackResult;
                }
            }

            // akce s vysledkem
            if ($opt['target_dir'] !== null) {
                // ulozeni
                $uid = isset($opt['target_uid']) ? $opt['target_uid'] : uniqid('');
                $target_format = $opt['target_format'] !== null ? $opt['target_format'] : $load['ext'];

                $target_path = static::get(
                    $opt['target_dir'],
                    $uid,
                    $target_format,
                    $opt['target_partitions']
                );

                $put = static::store(
                    $load['resource'],
                    $target_path,
                    $target_format,
                    $opt['jpg_quality']
                );
                if (!$put['status']) {
                    throw new \RuntimeException($put['msg']);
                }

                // smazani obrazku z pameti
                if ($opt['destroy']) {
                    imagedestroy($load['resource']);
                    $resource = null;
                } else {
                    $resource = $load['resource'];
                }

                // vratit UID
                return $uid;
            } else {
                // vratit resource
                return $load['resource'];
            }
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();

            return false;
        }
    }

    /**
     * Vygenerovat cachovanou miniaturu obrazku
     *
     * @param string $source          cesta ke zdrojovemu obrazku
     * @param array  $resize_opts     volby pro zmenu velikosti, {@see Picture::resize()} (mode je prednastaven na zoom)
     * @param bool   $use_error_image vratit chybovy obrazek pri neuspechu namisto false
     * @param string $error          promenna, kam bude ulozena pripadna chybova hlaska
     * @return string|bool cesta k miniature nebo chybovemu obrazku nebo false pri neuspechu
     */
    static function getThumbnail($source, array $resize_opts, $use_error_image = true, &$error = null)
    {
        // zjistit priponu
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (!in_array($ext, Core::$imageExt)) {
            return $use_error_image ? Core::$imageError : false;
        }

        // sestavit cestu do adresare
        $dir = 'images/thumb/';

        // extend pro nastaveni velikosti
        Extend::call('picture.thumb.resize', array('options' => &$resize_opts));

        // vychozi nastaveni zmenseni
        $resize_opts += array(
            'mode' => 'zoom',
            'trans' => $ext === 'png' || $ext === 'gif',
            'trans_format' => $ext,
        );

        // normalizovani nastaveni zmenseni
        if (!isset($resize_opts['x']) || $resize_opts['x'] == 0) {
            $resize_opts['x'] = null;
        } else {
            $resize_opts['x'] = (int) $resize_opts['x'];
        }
        if (!isset($resize_opts['y']) || $resize_opts['y'] == 0) {
            $resize_opts['y'] = null;
        } else {
            $resize_opts['y'] = (int) $resize_opts['y'];
        }

        // vygenerovat hash
        ksort($resize_opts);
        if (isset($resize_opts['bgcolor'])) {
            ksort($resize_opts['bgcolor']);
        }
        $hash = md5(realpath($source) . '$' . serialize($resize_opts));

        // sestavit cestu k obrazku
        $image_path = static::get($dir, $hash, $ext, 1);

        // zkontrolovat cache
        if (file_exists($image_path)) {
            // obrazek jiz existuje
            if (time() - filemtime($image_path) >= _thumb_touch_threshold) {
                touch($image_path);
            }

            return $image_path;
        } else {
            // obrazek neexistuje
            $options = array(
                'file_path' => $source,
                'resize' => $resize_opts,
                'target_dir' => $dir,
                'target_uid' => $hash,
                'target_partitions' => 1,
            );

            // extend
            Extend::call('picture.thumb.process', array('options' => &$options));

            // vygenerovat
            if (static::process($options, $error) !== false) {
                // uspech
                return $image_path;
            } else {
                // chyba
                return $use_error_image ? Core::$imageError : false;
            }
        }
    }

    /**
     * Smazat nepouzivane miniatury
     *
     * @param int $threshold minimalni doba v sekundach od posledniho vyzadani miniatury
     */
    static function cleanThumbnails($threshold)
    {
        foreach (Filesystem::createRecursiveIterator(_root . 'images/thumb', \RecursiveIteratorIterator::LEAVES_ONLY) as $thumb) {
            if (
                in_array(strtolower($thumb->getExtension()), Core::$imageExt)
                && time() - $thumb->getMTime() > $threshold
            ) {
                unlink($thumb->getPathname());
            }
        }
    }

    /**
     * Kontrola podpory formatu GD knihovnou
     *
     * @param string|null $check_format nazev formatu (jpg, jpeg, png , gif) jehoz podpora se ma zkontrolovat nebo null
     * @return bool
     */
    static function checkFormatSupport($check_format = null)
    {
        if (function_exists('gd_info')) {
            if (isset($check_format)) {
                $info = gd_info();
                $support = false;
                switch (strtolower($check_format)) {
                    case 'png':
                        if (isset($info['PNG Support']) && $info['PNG Support'] == true) {
                            $support = true;
                        }
                        break;
                    case 'jpg':
                    case 'jpeg':
                        if ((isset($info['JPG Support']) && $info['JPG Support'] == true) || (isset($info['JPEG Support']) && $info['JPEG Support'] == true)) {
                            $support = true;
                        }
                        break;
                    case 'gif':
                        if (isset($info['GIF Read Support']) && $info['GIF Read Support'] == true) {
                            $support = true;
                        }
                        break;
                }

                return $support;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * @param resource $res
     * @param string $format
     * @param string $target_path
     * @param int $jpg_quality
     * @return bool
     */
    protected static function write($res, $format, $target_path, $jpg_quality)
    {
        $tmpfile = Filesystem::createTmpFile();
        $result = false;

        switch ($format) {
            case 'jpg':
            case 'jpeg':
                $result = @imagejpeg($res, $tmpfile->getPathname(), $jpg_quality);
                break;

            case 'png':
                $result = @imagepng($res, $tmpfile->getPathname());
                break;

            case 'gif':
                $result = @imagegif($res, $tmpfile->getPathname());
                break;
        }

        if ($result && $tmpfile->move($target_path)) {
            return true;
        }

        $tmpfile->discard();

        return is_file($target_path);
    }
}
