SunLight CMS 8
==============

This is the official GIT repository of SunLight CMS (8.x branch).

Documentation, support and stable downloads are available at `sunlight-cms.cz <https://sunlight-cms.cz/>`_.


Requirements
************

- web server (apache preferred)
- PHP 5.3 or newer

  - extensions: mbstring, mysqli

- MySQL (or MariaDB) 5.0 or newer
- `Composer <https://getcomposer.org/>`_ (to install dependencies)


Installation
************

1. Download (and extract) or clone this repository locally.
2. Run ``composer install --prefer-dist`` in the root directory.
3. Open ``http://example.com/install/`` (change the domain and path accordingly) in your web browser.
4. Follow the on-screen instructions.


Legacy code notice
******************

This codebase is very old. It was originally written in PHP 4 and uses
very little OOP.

While it is functional and has no known security vulnerabilities, one
should keep this in mind when browsing through the code.
