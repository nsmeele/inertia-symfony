<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Validation;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Converts constraint violations into the flat `field => message` map Inertia expects.
 */
final class ValidationErrors
{
    /**
     * Keeps the first message per field and turns property paths like
     * `items[0].name` into the dot notation used by the Inertia form helpers.
     *
     * @return array<string, string>
     */
    public static function fromViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $field = self::field($violation->getPropertyPath());
            $errors[$field] ??= (string) $violation->getMessage();
        }

        return $errors;
    }

    /**
     * Same map for a submitted Symfony form (requires symfony/form), keyed by the field's path in the form.
     *
     * @return array<string, string>
     */
    public static function fromForm(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            $path = [];
            for ($field = $error->getOrigin(); $field !== null && !$field->isRoot(); $field = $field->getParent()) {
                array_unshift($path, $field->getName());
            }

            $errors[self::field(implode('.', $path))] ??= $error->getMessage();
        }

        return $errors;
    }

    public static function field(string $propertyPath): string
    {
        $field = ltrim(str_replace(['[', ']'], ['.', ''], $propertyPath), '.');

        return $field === '' ? '_global' : $field;
    }
}
