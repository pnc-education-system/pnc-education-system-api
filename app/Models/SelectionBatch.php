<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelectionBatch extends Model
{
    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
