<?php

namespace Amp\Injector;

interface ReflectionCache
{
    public function fetch(string $key): mixed;

    public function store(string $key, mixed $data): void;
}
