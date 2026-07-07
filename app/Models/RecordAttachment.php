<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordAttachment extends Model
{
    protected $fillable = [
        'student_record_id',
        'file_path',
        'file_type',
    ];

    public function studentRecord(): BelongsTo
    {
        return $this->belongsTo(StudentRecord::class);
    }
}
