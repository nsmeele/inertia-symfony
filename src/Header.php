<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony;

final class Header
{
    public const string INERTIA = 'X-Inertia';
    public const string VERSION = 'X-Inertia-Version';
    public const string LOCATION = 'X-Inertia-Location';
    public const string ERROR_BAG = 'X-Inertia-Error-Bag';
    public const string PARTIAL_COMPONENT = 'X-Inertia-Partial-Component';
    public const string PARTIAL_ONLY = 'X-Inertia-Partial-Data';
    public const string PARTIAL_EXCEPT = 'X-Inertia-Partial-Except';
    public const string RESET = 'X-Inertia-Reset';
}
