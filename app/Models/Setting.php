<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['section', 'key', 'value'];

    public static function getSection(string $section): array
    {
        return static::where('section', $section)
            ->pluck('value', 'key')
            ->map(fn ($value) => static::decodeStoredValue($value))
            ->all();
    }

    public static function decodeStoredValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed === '' || ! in_array($trimmed[0], ['{', '[', '"'], true)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public static function saveSection(string $section, array $data): void
    {
        foreach ($data as $key => $value) {
            static::updateOrCreate(
                ['section' => $section, 'key' => $key],
                ['value' => is_array($value) ? json_encode($value) : (string) $value],
            );
        }
    }
}
