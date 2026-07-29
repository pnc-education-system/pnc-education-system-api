<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    const STATUS_PENDING   = 'Pending';
    const STATUS_ENROLLED  = 'Enrolled';
    const STATUS_REJECTED  = 'Rejected';
    const STATUS_GRADUATED = 'Graduated';
    const STATUS_DROPPED   = 'Dropped';

    const VALID_TRANSITIONS = [
        self::STATUS_PENDING   => [self::STATUS_ENROLLED, self::STATUS_REJECTED, self::STATUS_DROPPED],
        self::STATUS_ENROLLED  => [self::STATUS_GRADUATED, self::STATUS_DROPPED],
        self::STATUS_REJECTED  => [self::STATUS_PENDING],
        self::STATUS_GRADUATED => [],
        self::STATUS_DROPPED   => [self::STATUS_PENDING],
    ];

    protected $fillable = [
        'student_id_no',
        'full_name',
        'gender',
        'dob',
        'phone',
        'email',
        'province',
        'high_school',
        'selection_batch_id',
        'enrollment_status',
        'is_confirmed',
        'intake_year',
        'enrolled_at',
        'photo_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'dob'          => 'date:Y-m-d',
            'enrolled_at'  => 'datetime',
            'intake_year'  => 'integer',
            'is_confirmed' => 'boolean',
        ];
    }

    public function selectionBatch(): BelongsTo
    {
        return $this->belongsTo(SelectionBatch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(StudentCard::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(StudentRecord::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function enrollmentStatusHistories(): HasMany
    {
        return $this->hasMany(EnrollmentStatusHistory::class);
    }

    public static function isValidTransition(string $from, string $to): bool
    {
        return isset(self::VALID_TRANSITIONS[$from]) && in_array($to, self::VALID_TRANSITIONS[$from], true);
    }

    public static function validTransitionsFrom(string $status): array
    {
        return self::VALID_TRANSITIONS[$status] ?? [];
    }

    public function transitionStatus(string $newStatus, ?string $note = null, ?int $changedBy = null, bool $force = false): EnrollmentStatusHistory
    {
        $oldStatus = $this->enrollment_status;

        if (!$force && !self::isValidTransition($oldStatus, $newStatus)) {
            throw new \InvalidArgumentException(
                "Invalid status transition from '{$oldStatus}' to '{$newStatus}'."
            );
        }

        $this->enrollment_status = $newStatus;

        // Auto-set enrolled_at when transitioning to Enrolled
        if ($newStatus === self::STATUS_ENROLLED && $oldStatus !== self::STATUS_ENROLLED) {
            $this->enrolled_at = now();
        } elseif ($oldStatus === self::STATUS_ENROLLED && $newStatus !== self::STATUS_ENROLLED) {
            $this->enrolled_at = null;
        }

        $this->save();

        return $this->enrollmentStatusHistories()->create([
            'old_status'       => $oldStatus,
            'new_status'       => $newStatus,
            'graduated_status' => $newStatus,
            'note'             => $note,
            'changed_by'       => $changedBy ?? auth()->id(),
        ]);
    }
}
