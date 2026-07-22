<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Resources\IdCardResource;
use App\Services\Student\IdCardService;
use Illuminate\Http\JsonResponse;

/**
 * IdCardController
 *
 * Handles requests for viewing a student's digital ID card data.
 * Follows the Controller → Service → Repository → Resource layered architecture.
 */
class IdCardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected IdCardService $idCardService,
    ) {}

    /**
     * Retrieve the digital ID card information for a given student.
     *
     * GET /api/v1/students/{id}/id-card
     *
     * @param  int  $id  The student's primary key.
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $cardData = $this->idCardService->getCardData($id);

        if ($cardData === null) {
            return $this->error('Student not found', 404);
        }

        return response()->json([
            'data' => new IdCardResource($cardData),
        ], 200);
    }
}
