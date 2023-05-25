<?php

namespace Sunlight\Admin;

class AdminState
{
    /** @var array */
    public $modules;
    /** @var string */
    public $currentModule;
    /** @var array<string, int>  */
    public $menu;
    /** @var bool */
    public $loginLayout = false;
    /** @var bool */
    public $wysiwygAvailable = false;
    /** @var string[] */
    public $bodyClasses = [];
    /** @var bool */
    public $access;
    /** @var string|null */
    public $redirectTo;
    /** @var string|null */
    public $title;
    /** @var array|null */
    public $assets;
    /** @var bool */
    public $dark = false;
    /** @var string */
    public $output = '';

    function redirect(string $url): void
    {
        $this->redirectTo = $url;
    }
}
