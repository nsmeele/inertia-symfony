<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Props;

/** Never included on first load; only when explicitly requested by a partial reload. */
final readonly class OptionalProp
{
    public function __construct(private \Closure $callback)
    {
    }

    public function resolve(): mixed
    {
        return ($this->callback)();
    }
}
