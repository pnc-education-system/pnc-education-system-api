<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentCardRequest;
use App\Http\Resources\StudentCardResource;
use App\Http\Resources\StudentProfileResource;
use App\Services\Student\StudentCardService;
use Illuminate\Http\Request;

class StudentCardController extends Controller
{
    protected StudentCardService $studentCardService;

    public function __construct(StudentCardService $studentCardService)
    {
        $this->studentCardService = $studentCardService;
    }

    public function store(StoreStudentCardRequest $request)
    {
        $card = $this->studentCardService->createStudentCard($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Student card created successfully.',
            'data' => new StudentCardResource($card),
        ], 201);
    }

    public function resolveQr(string $qr_token)
    {
        $student = $this->studentCardService->findStudentByQrToken($qr_token);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired QR token.',
            ], 404);
        }

        $student->load('cards');

        return response()->json([
            'success' => true,
            'message' => 'Student profile retrieved successfully.',
            'data' => new StudentProfileResource($student),
        ]);
    }
}
