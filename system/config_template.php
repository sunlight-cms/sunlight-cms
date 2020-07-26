<?php

return array(
    // pristup k databazi
    // database access
    'db.server' => '@@db.server@@',
    'db.port' => '@@db.port|null@@',
    'db.user' => '@@db.user@@',
    'db.password' => '@@db.password@@',
    'db.name' => '@@db.name@@',
    'db.prefix' => '@@db.prefix@@',

    // abs. adresa bez lomitka na konci
    // absolute URL without a trailing slash
    'url' => '@@url@@',

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
    'geo.latitude' => '@@geo.latitude|50.5@@',
    'geo.longitude' => '@@geo.longitude|14.26@@',
    'geo.zenith' => '@@geo.zenith|90.583333@@',
);
