<?php

namespace Sunlight;

use Kuria\Url\Url;
use Sunlight\Util\HttpClient;
use Sunlight\Util\HttpClientException;

class VersionChecker
{
    /** @var array|null */
    private static $data;

    /** @var bool */
    private static $loaded = false;

    /**
     * @return array{latestVersion: string, localAge: int<-1, 3>, url: string}|null
     */
    static function check(): ?array
    {
        if (!self::$loaded) {
            self::loadData();
        }

        return self::$data;
    }

    private static function loadData(): void
    {
        self::$loaded = true;

        if (!Settings::get('version_check')) {
            return;
        }

        if (!Core::isCacheEnabled()) {
            // don't hit the API if cache is disabled
            return;
        }

        $data = Core::$cache->cached('version_checker', 7 * 24 * 60 * 60, function () {
            $versionApiUrl = Url::parse('https://api.sunlight-cms.cz/8.x/version-check');
            $versionApiUrl->add([
                'ver' => Core::VERSION,
                'php' => PHP_VERSION_ID,
                'checksum' => hash_hmac('sha1', __FILE__, Core::$secret),
                'lang' => Core::$langPlugin->getIsoCode(),
            ]);

            $baseUrl = Core::getBaseUrl();

            try {
                $response = HttpClient::get($versionApiUrl->build(), [
                    'headers' => [sprintf('Referer: %s://%s', $baseUrl->getScheme() ?? 'http', $baseUrl->getFullHost())],
                    'timeout' => 1,
                ]);
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
