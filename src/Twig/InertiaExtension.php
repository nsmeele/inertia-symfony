<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class InertiaExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('inertia', $this->render(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, mixed> $page
     */
    public function render(array $page, string $id = 'app'): string
    {
        return sprintf(
            '<div id="%s" data-page="%s"></div>',
            htmlspecialchars($id, ENT_QUOTES),
            htmlspecialchars(json_encode($page, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ENT_QUOTES),
        );
    }
}
