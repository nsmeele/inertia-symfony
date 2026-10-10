<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateUser
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name = '',
        #[Assert\Email]
        public string $email = '',
    ) {
    }
}
