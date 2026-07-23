<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentRecord extends Model
{
    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_NOTE = 'note';
    public const CATEGORY_INCIDENT = 'incident';
    public const CATEGORY_ACHIEVEMENT = 'achievement';
    public const CATEGORY_ACADEMIC = 'academic';
    public const CATEGORY_DISCIPLINARY = 'disciplinary';
    public const CATEGORY_MEDICAL = 'medical';

    public const CATEGORIES = [
        self::CATEGORY_GENERAL,
        self::CATEGORY_NOTE,
        self::CATEGORY_INCIDENT,
        self::CATEGORY_ACHIEVEMENT,
        self::CATEGORY_ACADEMIC,
        self::CATEGORY_DISCIPLINARY,
        self::CATEGORY_MEDICAL,
    ];

    protected $fillable = [
        'student_id',
        'category',
        'title',
        'description',
        'record_date',
        'created_by',
    ];

    // ── Field mapping helpers ──
    /** Map frontend field names to backend field names */
    public static function mapFrontendFields(array $data): array
    {
        $mapped = $data;

        // record_type -> category
        if (isset($mapped['record_type']) && !isset($mapped['category'])) {
            $mapped['category'] = $mapped['record_type'];
        }
        unset($mapped['record_type']);

        // recorded_at -> record_date
        if (isset($mapped['recorded_at']) && !isset($mapped['record_date'])) {
            $mapped['record_date'] = $mapped['recorded_at'];
        }
        unset($mapped['recorded_at']);

        // recorded_by -> created_by
        if (isset($mapped['recorded_by'])) {
            $mapped['created_by'] = $mapped['recorded_by'];
        }
        unset($mapped['recorded_by']);

        return $mapped;
    }

    /** Map backend fields to frontend response fields */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'title' => $this->title,
            'description' => $this->description,
            'record_type' => $this->category,
            'category' => $this->category,
            'recorded_by' => $this->created_by,
            'created_by' => $this->created_by,
            'recorded_at' => $this->record_date ? Carbon::parse($this->record_date)->toISOString() : $this->created_at,
            'record_date' => $this->record_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'attachments' => $this->relationLoaded('attachments') ? $this->attachments : [],
        ];
    }

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
