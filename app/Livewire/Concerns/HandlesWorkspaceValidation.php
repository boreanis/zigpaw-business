<?php

namespace App\Livewire\Concerns;

use Livewire\Component;

/** @mixin Component */
trait HandlesWorkspaceValidation
{
    /** @param array<string, list<string>> $errors @param array<string, string> $fields */
    protected function mapWorkspaceValidationErrors(array $errors, array $fields): void
    {
        foreach ($errors as $field => $messages) {
            $property = 'workspace';
            foreach ($fields as $prefix => $formField) {
                if ($field === $prefix || str_starts_with($field, $prefix.'.')) {
                    $property = $formField.substr($field, strlen($prefix));
                    break;
                }
            }
            foreach ($messages as $message) {
                $this->addError($property, $message);
            }
        }
    }
}
