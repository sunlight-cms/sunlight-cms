<?php


namespace Sunlight;

use Sunlight\Util\HttpClient;
use Sunlight\Util\HttpClientException;
use Sunlight\Util\Url;

class VersionChecker
{
    /** @var array|null */
    private static $data;

    /** @var bool */
    private static $loaded = false;

    /**
     * @return array|null
     */
    static function check()
    {
        if (!self::$loaded) {
            self::loadData();
        }

        return self::$data;
    }

    private static function loadData()
    {
        self::$loaded = true;

        if (!_version_check) {
            return;
        }

        $data = Core::$cache->cached('version_checker', function (&$ttl) {
            $ttl = 7 * 24 * 60 * 60;

            $versionApiUrl = Url::parse('https://api.sunlight-cms.cz/version');
            $versionApiUrl->add(array(
                'ver' => Core::VERSION,
                'dist' => Core::DIST,
                'php' => PHP_VERSION_ID,
                'checksum' => sha1(Core::$appId . '$' . Core::$secret),
                'lang' => _lang('langcode.iso639'),
            ));

            try {
                $response = HttpClient::get($versionApiUrl->generate(), array(
                    'headers' => array(sprintf('Referer: %s', Core::$url)),
                    'timeout' => 1,
                ));
            } catch (HttpClientException $e) {
                return null;
            }

            $response = json_decode($response, true);

            if (!is_array($response)) {
                return null;
            }

            return $response;
        });

        if ($data !== false) {
            self::$data = $data;
        }
    }
}