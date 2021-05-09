<?php

namespace Amp\Injector;

function arguments(): ArgumentRules
{
    static $rules = null;

    if (!$rules) {
        $rules = new ArgumentRules;
    }

    return $rules;
}