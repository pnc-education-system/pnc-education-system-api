<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardTemplate extends Model
{
    protected $fillable = [
        'title',
        'description',
    ];

    public function studentCards(): HasMany
    {
        return $this->hasMany(StudentCard::class);
    }
}
