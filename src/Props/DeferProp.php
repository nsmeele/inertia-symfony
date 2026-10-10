<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Props;

/** Left out of the first response; the client fetches it right after the initial render. */
final readonly class DeferProp
{
    public function __construct(
        private \Closure $callback,
        public string $group = 'default',
    ) {
    }

    public function resolve(): mixed
    {
        return ($this->callback)();
    }
}
