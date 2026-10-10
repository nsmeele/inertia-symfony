<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony;

use Nsmeele\InertiaSymfony\Props\AlwaysProp;
use Nsmeele\InertiaSymfony\Props\DeferProp;
use Nsmeele\InertiaSymfony\Props\MergeProp;
use Nsmeele\InertiaSymfony\Props\OptionalProp;
use Symfony\Component\HttpFoundation\Request;

/**
 * Applies the partial reload rules of the Inertia protocol and resolves lazy props.
 */
final class PropsResolver
{
    /**
     * @param array<string, mixed> $props
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} resolved props and page metadata
     */
    public function resolve(Request $request, string $component, array $props): array
    {
        $isPartial = $request->headers->get(Header::PARTIAL_COMPONENT) === $component;
        $only = $isPartial ? $this->header($request, Header::PARTIAL_ONLY) : [];
        $except = $isPartial ? $this->header($request, Header::PARTIAL_EXCEPT) : [];
        $reset = $this->header($request, Header::RESET);

        $resolved = [];
        $mergeProps = [];
        $deepMergeProps = [];
        $deferred = [];

        foreach ($props as $key => $prop) {
            $requested = $this->isRequested((string) $key, $only, $except);

            if ($prop instanceof DeferProp) {
                if (!$isPartial || !in_array($key, $only, true)) {
                    $deferred[$prop->group][] = $key;

                    continue;
                }
            } elseif ($prop instanceof OptionalProp) {
                if (!in_array($key, $only, true)) {
                    continue;
                }
            } elseif (!$prop instanceof AlwaysProp && !$requested) {
                continue;
            }

            if ($prop instanceof MergeProp && !in_array($key, $reset, true)) {
                $prop->deep ? $deepMergeProps[] = $key : $mergeProps[] = $key;
            }

            $resolved[$key] = $this->resolveValue($prop);
        }

        $metadata = [];
        if ($mergeProps !== []) {
            $metadata['mergeProps'] = $mergeProps;
        }
        if ($deepMergeProps !== []) {
            $metadata['deepMergeProps'] = $deepMergeProps;
        }
        if ($deferred !== []) {
            $metadata['deferredProps'] = $deferred;
        }

        return [$resolved, $metadata];
    }

    /**
     * @param list<string> $only
     * @param list<string> $except
     */
    private function isRequested(string $key, array $only, array $except): bool
    {
        if ($only !== [] && !in_array($key, $only, true)) {
            return false;
        }

        return !in_array($key, $except, true);
    }

    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof AlwaysProp || $value instanceof OptionalProp
            || $value instanceof DeferProp || $value instanceof MergeProp) {
            return $this->resolveValue($value->resolve());
        }

        if ($value instanceof \Closure) {
            return $this->resolveValue($value());
        }

        if (is_array($value)) {
            return array_map($this->resolveValue(...), $value);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function header(Request $request, string $name): array
    {
        $value = $request->headers->get($name);

        return $value === null || $value === '' ? [] : array_map('trim', explode(',', $value));
    }
}
