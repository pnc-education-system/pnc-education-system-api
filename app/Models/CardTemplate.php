<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'layout_json',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function studentCards(): HasMany
    {
        return $this->hasMany(StudentCard::class, 'template_id');
    }
}
