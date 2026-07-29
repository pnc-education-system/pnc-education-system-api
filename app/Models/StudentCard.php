<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'template_id',
        'card_number',
        'qr_token',
        'issued_at',
        'issued_date',
        'expired_date',
        'printed_count',
        'pdf_path',
    ];

    protected static function booted(): void
    {
        static::creating(function ($card) {
            if (empty($card->qr_token)) {
                $card->qr_token = self::generateUniqueQrToken();
            }
            if (empty($card->card_number)) {
                $card->card_number = self::generateCardNumber();
            }
            if (empty($card->issued_date)) {
                $card->issued_date = now()->toDateString();
            }
        });
    }

    public static function generateUniqueQrToken(): string
    {
        do {
            $token = (string) Str::uuid();
        } while (self::where('qr_token', $token)->exists());

        return $token;
    }

    public static function generateCardNumber(): string
    {
        do {
            $number = 'CARD-' . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('card_number', $number)->exists());

        return $number;
    }

    protected function casts(): array
    {
        return [
            'issued_at'     => 'datetime',
            'issued_date'   => 'date',
            'expired_date'  => 'date',
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
