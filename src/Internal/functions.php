<?php

namespace Amp\Injector\Internal;

/** @internal */
function normalizeClass(string $class): string
{
    // See https://www.php.net/manual/en/language.oop5.basic.php
    if (!\preg_match('(^\\\\?(?:[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*\\\\)*[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$)', $class)) {
        throw new \Error('Invalid class name: ' . $class);
    }

    return \strtolower(\ltrim($class, '\\'));
}

/** @internal */
function getDefaultReflector(): Reflector
{
    static $reflector = null;

    if (!$reflector) {
        $reflector = new CachingReflector(new StandardReflector);
    }

    return $reflector;
}
