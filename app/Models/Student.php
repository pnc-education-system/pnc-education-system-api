<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'student_code',
        'first_name',
        'last_name',
        'gender',
        'dob',
        'email',
        'phone',
        'enrollment_status',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SelectionBatch::class, 'batch_id');
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
