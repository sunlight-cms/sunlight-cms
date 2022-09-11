<?php

use Sunlight\Captcha\Text3dCaptcha;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Image\ImageFormat;
use Sunlight\Image\ImageTransformer;
use Sunlight\Util\Request;
use Sunlight\Util\Response;

require '../../bootstrap.php';
Core::init('../../../', [
    'content_type' => false,
]);

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
    'w' => floor($captcha->width / 2),
    'h' => floor($captcha->height / 2),
    'mode' => 'fit',
];

Extend::call('captcha.render.resize', [
    'captcha' => $captcha,
    'options' => &$captchaResizeOptions,
]);

$captcha = ImageTransformer::resize($captcha, $captchaResizeOptions);

// output image
header('Content-Type: image/jpeg');
$captcha->output(ImageFormat::JPG, ['jpg_quality' => 50]);
