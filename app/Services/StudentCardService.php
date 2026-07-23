<?php

namespace App\Services;

use App\Models\StudentCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StudentCardService
{
    public function reprint(int $cardId): StudentCard
    {
        $card = StudentCard::findOrFail($cardId);
        $card->increment('printed_count');

        return $card->fresh();
    }
}
