<?php

namespace App\Http\Controllers\Api\V1\Card;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Resources\StudentCardResource;
use App\Services\StudentCardService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StudentCardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StudentCardService $cardService,
    ) {}

    /**
     * Increment the printed_count of the given student card.
     *
     * POST /api/v1/student-cards/{id}/reprint
     */
    public function reprint(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $card = $this->cardService->reprint($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Card reprinted successfully',
                'data'    => new StudentCardResource($card),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->error('Student card not found', 404);
        }
    }
}
