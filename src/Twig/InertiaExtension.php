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
     * Inertia v3 reads the initial page from `<script data-page="{id}" type="application/json">`
     * and mounts into `<div id="{id}">`. The JSON is not HTML-escaped, so `<`, `>` and `&`
     * are escaped to keep it from closing the script element.
     *
     * @param array<string, mixed> $page
     */
    public function render(array $page, string $id = 'app'): string
    {
        $id = htmlspecialchars($id, ENT_QUOTES);
        $json = json_encode($page, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP);

        return sprintf('<script data-page="%1$s" type="application/json">%2$s</script><div id="%1$s"></div>', $id, $json);
    }
}
