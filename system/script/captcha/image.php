<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Captcha\Text3dCaptcha;

require '../../bootstrap.php';
Core::init('../../../', array(
    'content_type' => false,
));

// check GD
if (!_checkGD('jpg')) {
    Core::systemFailure(
        'Není dostupná GD knihovna pro generování obrázků nebo nepodporuje JPG formát.',
        'The GD library needed to generate JPG images is not available or does not support this format.'
    );
}

// fetch code
$captchaNumber = _get('n');

if (!empty($captchaNumber) && isset($_SESSION['captcha_code'][$captchaNumber])) {
    list($captchaCode, $captchaDrawn) = $_SESSION['captcha_code'][$captchaNumber];

    if ($captchaDrawn) {
        header('HTTP/1.0 403 Forbidden');
        exit;
    }
    //$_SESSION['captcha_code'][$captchaNumber][1] = true;
} else {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

// generate image
$captchaRenderer = new Text3dCaptcha();
$captchaRenderer->setLetterSpacing(2);

Extend::call('captcha.render', array(
    'renderer' => &$captchaRenderer,
    'code' => &$captchaCode,
));

$captcha = $captchaRenderer->draw($captchaCode);

$captchaResizeOptions = array(
    'x' => floor(imagesx($captcha) / 2),
    'y' => floor(imagesy($captcha) / 2),
    'mode' => 'fit',
);

Extend::call('captcha.render.resize', array(
    'captcha' => $captcha,
    'options' => &$captchaResizeOptions,
));

$resizedCaptcha = _pictureResize($captcha, $captchaResizeOptions);

if (!$resizedCaptcha['status']) {
    throw new \RuntimeException('Could not resize CAPTCHA image');
}

// output image
header('Content-Type: image/jpg');
imagejpeg($resizedCaptcha['resource'], null, 50);
