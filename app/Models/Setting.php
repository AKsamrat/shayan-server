<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['group', 'key', 'value', 'type'];

    protected function casts(): array
    {
        return [
            'value' => 'string',
        ];
    }

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;

        return match ($setting->type) {
            'boolean' => (bool) $setting->value,
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Set a setting value by key. Creates if not exists.
     */
    public static function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string'): static
    {
        $encoded = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $encoded, 'group' => $group, 'type' => $type]
        );
    }

    /**
     * Get all settings for a group as key => value array.
     */
    public static function getGroup(string $group): array
    {
        $settings = static::where('group', $group)->get();
        $result = [];
        foreach ($settings as $s) {
            $result[$s->key] = match ($s->type) {
                'boolean' => (bool) $s->value,
                'integer' => (int) $s->value,
                'json' => json_decode($s->value, true),
                default => $s->value,
            };
        }
        return $result;
    }

    /**
     * Set multiple settings for a group at once.
     */
    public static function setGroup(string $group, array $data): void
    {
        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'boolean'
                : (is_int($value) ? 'integer'
                    : (is_array($value) ? 'json'
                        : 'string'));
            static::setValue($key, $value, $group, $type);
        }
    }
}
