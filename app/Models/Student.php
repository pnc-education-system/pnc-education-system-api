<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
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
    'intake_year',
    'photo_path',
    'created_by',
];

    protected function casts(): array
    {
        return [
            'dob'        => 'date:Y-m-d',
            'intake_year' => 'integer',
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
}
