<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentRecord extends Model
{
    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_NOTE = 'note';
    public const CATEGORY_INCIDENT = 'incident';
    public const CATEGORY_ACHIEVEMENT = 'achievement';

    public const CATEGORIES = [
        self::CATEGORY_GENERAL,
        self::CATEGORY_NOTE,
        self::CATEGORY_INCIDENT,
        self::CATEGORY_ACHIEVEMENT,
    ];

    protected $fillable = [
        'student_id',
        'category',
        'title',
        'description',
        'record_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date:Y-m-d',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RecordAttachment::class);
    }
}
