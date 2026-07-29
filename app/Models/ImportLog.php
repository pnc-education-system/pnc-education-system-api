<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportLog extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'selection_batch_id',
        'total_rows',
        'success_count',
        'error_count',
        'status',
        'imported_by',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SelectionBatch::class, 'selection_batch_id');
    }
}
