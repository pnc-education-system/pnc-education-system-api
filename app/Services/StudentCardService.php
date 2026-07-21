<?php

namespace App\Services;

use App\Models\StudentCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StudentCardService
{
    /**
     * Increment the printed_count for a student card by 1.
     *
     * Uses atomic increment() to avoid race conditions.
     *
     * @throws ModelNotFoundException
     */
    public function reprint(int $cardId): StudentCard
    {
        $card = StudentCard::findOrFail($cardId);

        /** @var StudentCard $card */
        $card->increment('printed_count');

        return $card->fresh();
    }
}
