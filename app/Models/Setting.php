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
            ->map(fn ($v) => json_decode($v, true) ?? $v)
            ->all();
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
