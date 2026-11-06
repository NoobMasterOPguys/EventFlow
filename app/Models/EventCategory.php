<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'color', 'icon', 'is_active'])]
class EventCategory extends Model
{
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category', 'name');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
