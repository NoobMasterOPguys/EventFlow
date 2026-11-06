<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'updated_by'])]
class SystemSetting extends Model
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value === null ? $default : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    public static function put(string $key, mixed $value, ?User $actor = null): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'updated_by' => $actor?->getKey(),
            ],
        );
    }

    public static function apply(): void
    {
        $applicationName = static::get('application_name', config('app.name'));
        $timezone = static::get('timezone', config('app.timezone'));

        if (is_string($applicationName) && $applicationName !== '') {
            config(['app.name' => $applicationName]);
        }

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
