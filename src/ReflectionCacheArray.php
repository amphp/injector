<?php

namespace Amp\Injector;

class ReflectionCacheArray implements ReflectionCache
{
    private array $cache = [];

    public function fetch(string $key): mixed
    {
        return \array_key_exists($key, $this->cache) ? $this->cache[$key] : false;
    }

    public function store(string $key, mixed $data): void
    {
        $this->cache[$key] = $data;
    }
}
