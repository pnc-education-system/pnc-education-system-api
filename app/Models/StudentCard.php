<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCard extends Model
{
    protected $fillable = [
        'student_id',
        'template_id',
        'card_number',
        'qr_token',
        'issued_at',
        'printed_count',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'     => 'datetime',
            'printed_count' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cardTemplate(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'template_id');
    }
}
