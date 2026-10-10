<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Props;

/** Included in every response, also during partial reloads that did not ask for it. */
final readonly class AlwaysProp
{
    public function __construct(private mixed $value)
    {
    }

    public function resolve(): mixed
    {
        return is_callable($this->value) ? ($this->value)() : $this->value;
    }
}
