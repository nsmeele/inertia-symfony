<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Props;

/** Tells the client to merge the value into the existing prop instead of replacing it. */
final readonly class MergeProp
{
    public function __construct(
        private mixed $value,
        public bool $deep = false,
    ) {
    }

    public function resolve(): mixed
    {
        return is_callable($this->value) ? ($this->value)() : $this->value;
    }
}
