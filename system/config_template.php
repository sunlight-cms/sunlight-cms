<?php

return [
    // pristup k databazi
    // database access
    'db.server' => '@@db.server@@',
    'db.port' => '@@db.port|null@@',
    'db.user' => '@@db.user@@',
    'db.password' => '@@db.password@@',
    'db.name' => '@@db.name@@',
    'db.prefix' => '@@db.prefix@@',

    // nahodny tajny hash (pouzivano pro XSRF ochranu aj.)
    // random secret hash (used for XSRF protection etc.)
    // https://sunlight-cms.cz/resource/hashgen
    'secret' => '@@secret@@',

    // unikatni identifikator v ramci serveru (pouzivano pro nazev session, cookies, aj.)
    // unique identifier (server-wide) (used as part of the session name, cookies, etc.)
    'app_id' => '@@app_id|sunlight@@',

    // vychozi jazyk (cs nebo en)
    // default language (cs or en)
    'fallback_lang' => '@@fallback_lang|cs@@',

    // nastaveni duveryhodnych proxy serveru (pokud jsou vase stranky za proxy serverem; potreba pro detekci URL a IP)
    // trusted proxy configuration (if your site is behind a proxy; needed for correct URL and IP detection)
    'trusted_proxies' => null, // ['10.20.30.40'] - list of IPs (can use CIDR notation)
    'trusted_proxy_headers' => null, // 'forwarded' / 'x-forwarded' / 'all'

    // vyvojovy rezim (nepouzivat v produkci)
    // debug mode (do not use in production)
    'debug' => '@@debug|false@@',

    // pouzivat cache (doporuceno)
    // use cache (recommended)
    'cache' => '@@cache|true@@',

    // nastaveni lokalizace
    // localisation settings
    'locale' => '@@locale|null@@', // setlocale()
    'timezone' => '@@timezone|null@@', // date_default_timezone_set()
];
