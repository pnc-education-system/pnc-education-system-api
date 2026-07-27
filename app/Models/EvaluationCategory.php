<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationCategory extends Model
{
    protected $fillable = [
        'evaluation_form_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function evaluationForm(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EvaluationQuestion::class, 'category_id');
    }
}
