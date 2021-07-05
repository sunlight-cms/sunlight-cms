<?php

namespace Sunlight;

class WebState
{
    /** Page */
    const PAGE = 0;

    /** Plugin output */
    const PLUGIN = 1;

    /** Module */
    const MODULE = 2;

    /** Redirection */
    const REDIR = 3;

    /** 404 */
    const NOT_FOUND = 4;

    /** 401 */
    const UNAUTHORIZED = 5;

    /** @var int output type (see WebController::* constants) */
    public $type;

    /** @var int|null numeric identifier */
    public $id;

    /** @var string|null string identifier */
    public $slug;

    /** @var string|null part of the string identifier parsed as a segment */
    public $segment;

    /** @var string content URL */
    public $url;

    /** @var string|null HTML title (<title>) */
    public $title;

    /** @var string|null meta description */
    public $description;

    /** @var string|null top level heading (<h1>) */
    public $heading;

    /** @var bool top level heading toggle */
    public $headingEnabled = true;

    /** @var string|null back link URL */
    public $backlink;

    /** @var array<array{title: string, url: string}> */
    public $crumbs = [];

    /** @var bool actual URL type */
    public $isRewritten = false;

    /** @var string|null redirection target */
    public $redirectTo;

    /** @var bool permanent redirection 1/0 */
    public $redirectToPermanent = false;

    /** @var bool template toggle */
    public $templateEnabled = true;

    /** @var string[] classes to put on <body> */
    public $bodyClasses = [];

    /** @var string the content */
    public $output = '';

    /**
     * Set output to redirection
     */
    function redirect(string $url, bool $permament = false): void
    {
        $this->type = self::REDIR;
        $this->redirectTo = $url;
        $this->redirectToPermanent = $permament;
    }

    /**
     * Set output to a 404 page
     */
    function notFound(): void
    {
        $this->type = self::NOT_FOUND;
    }

    /**
     * Set output to a 403 page
     */
    function unauthorized(): void
    {
        $this->type = self::UNAUTHORIZED;
    }
}
