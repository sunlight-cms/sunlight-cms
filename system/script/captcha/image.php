<?php

use Sunlight\Captcha\Text3dCaptcha;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Picture;
use Sunlight\Util\Request;
use Sunlight\Util\Response;

require '../../bootstrap.php';
Core::init('../../../', [
    'content_type' => false,
]);

// check GD
if (!Picture::checkFormatSupport('jpg')) {
    Core::systemFailure(
        'Není dostupná GD knihovna pro generování obrázků nebo nepodporuje JPG formát.',
        'The GD library needed to generate JPG images is not available or does not support this format.'
    );
}

// fetch code
$captchaNumber = Request::get('n');

if (!empty($captchaNumber) && isset($_SESSION['captcha_code'][$captchaNumber])) {
    [$captchaCode, $captchaDrawn] = $_SESSION['captcha_code'][$captchaNumber];

    if ($captchaDrawn) {
        Response::forbidden();
        exit;
    }
    $_SESSION['captcha_code'][$captchaNumber][1] = true;
} else {
    Response::forbidden();
    exit;
}

// generate image
$captchaRenderer = new Text3dCaptcha();
$captchaRenderer->setLetterSpacing(2);

Extend::call('captcha.render', [
    'renderer' => &$captchaRenderer,
    'code' => &$captchaCode,
]);

$captcha = $captchaRenderer->draw($captchaCode);

$captchaResizeOptions = [
    'x' => floor(imagesx($captcha) / 2),
    'y' => floor(imagesy($captcha) / 2),
    'mode' => 'fit',
];

Extend::call('captcha.render.resize', [
    'captcha' => $captcha,
    'options' => &$captchaResizeOptions,
]);

$resizedCaptcha = Picture::resize($captcha, $captchaResizeOptions);

if (!$resizedCaptcha['status']) {
    throw new \RuntimeException('Could not resize CAPTCHA image');
}

// output image
header('Content-Type: image/jpeg');
imagejpeg($resizedCaptcha['resource'], null, 50);
