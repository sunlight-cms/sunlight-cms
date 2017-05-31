SunLight CMS 8
==============

This is the official GIT repository of SunLight CMS (8.x branch).

Documentation, support and stable downloads are available at `sunlight-cms.org <https://sunlight-cms.org/>`_.


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

This codebase is, as of writing, more than 10 years old. It was originally
written for PHP 4 and uses very little OOP (as it was my very first big
project).

While it is functional and secure, one should keep this in mind when
browsing through the code :)
