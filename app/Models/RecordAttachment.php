<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordAttachment extends Model
{
    protected $fillable = [
        'student_record_id',
        'student_id',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function studentRecord(): BelongsTo
    {
        return $this->belongsTo(StudentRecord::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get a human-readable file name from the stored path.
     */
    public function getFileNameAttribute(): string
    {
        $basename = basename($this->file_path);
        // If the stored name looks like a hashed/stored name (no extension or uuid-like),
        // derive a friendlier name from the record context
        if (!pathinfo($basename, PATHINFO_EXTENSION) && $this->file_type) {
            $ext = explode('/', $this->file_type)[1] ?? 'bin';
            return "attachment_{$this->id}.{$ext}";
        }
        return $basename;
    }

    /**
     * Return attachment data in the format expected by the frontend.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id ?? $this->studentRecord?->student_id,
            'record_id' => $this->student_record_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size ?? 0,
            'mime_type' => $this->file_type ?? 'application/octet-stream',
            'file_type' => $this->file_type,
            'uploaded_by' => $this->uploaded_by,
            'uploaded_at' => $this->created_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
