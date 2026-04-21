<?php

namespace App\Services\Integrations;

class FacebookLeadMapper
{
    public function extractLeadFields(array $leadData): array
    {
        $fields = [];
        $raw = $leadData['field_data'] ?? $leadData['fields'] ?? [];

        foreach ($raw as $key => $item) {
            if (is_string($key) && !is_array($item)) {
                $fields[$key] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $name = $item['name'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                continue;
            }

            $value = $item['values'][0] ?? ($item['value'] ?? null);
            $fields[$name] = is_string($value) ? trim($value) : $value;
        }

        return $fields;
    }

    public function resolveMappedLeadValue(array $fields, array $mapping, string $targetField, array $fallbackKeys = []): mixed
    {
        $targetField = trim($targetField);

        if (isset($mapping[$targetField]) && is_string($mapping[$targetField])) {
            $sourceKey = trim($mapping[$targetField]);
            if ($sourceKey !== '' && array_key_exists($sourceKey, $fields)) {
                return $fields[$sourceKey];
            }
        }

        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target)) {
                continue;
            }

            if (trim($target) === $targetField && array_key_exists($source, $fields)) {
                return $fields[$source];
            }
        }

        foreach ($fallbackKeys as $key) {
            if (array_key_exists($key, $fields)) {
                return $fields[$key];
            }
        }

        return null;
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
