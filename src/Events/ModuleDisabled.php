<?php

namespace RCV\Core\Events;

class ModuleDisabled
{
    public $moduleName;

    public function __construct($moduleName)
    {
        $this->moduleName = $moduleName;
    }
} 