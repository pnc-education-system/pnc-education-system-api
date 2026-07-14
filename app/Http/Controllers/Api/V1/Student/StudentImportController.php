<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\ImportValidationService;
use App\Services\Student\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentImportController extends Controller
{
    private StudentImportService $importService;
    private ImportValidationService $validationService;

    public function __construct(
        StudentImportService $importService,
        ImportValidationService $validationService
    ) {
        $this->importService = $importService;
        $this->validationService = $validationService;
    }

    public function validate(Request $request): JsonResponse
    {
        $validation = $this->validateRequest($request, $this->getValidateRules());

        if ($validation->fails()) {
            return $this->validationErrorResponse($validation);
        }

        $result = $this->validationService->validate($request->input('rows'));

        return $this->successResponse('Validation completed', $result);
    }

    public function import(Request $request): JsonResponse
    {
        $validation = $this->validateRequest($request, $this->getImportRules());

        if ($validation->fails()) {
            return $this->validationErrorResponse($validation);
        }

        try {
            $result = $this->importService->import(
                $request->input('rows'),
                $request->input('file_name'),
                auth()->id()
            );

            return $this->successResponse('Import completed successfully', $result);
        } catch (\Exception $e) {
            return $this->errorResponse('Import failed', $e->getMessage(), 500);
        }
    }

    private function getValidateRules(): array
    {
        return [
            'rows' => 'required|array',
            'rows.*' => 'array',
        ];
    }

    private function getImportRules(): array
    {
        return [
            'rows' => 'required|array',
            'rows.*' => 'array',
            'file_name' => 'required|string|max:255',
        ];
    }

    private function validateRequest(Request $request, array $rules)
    {
        return Validator::make($request->all(), $rules);
    }

    private function validationErrorResponse($validator): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    private function successResponse(string $message, $data = null): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    private function errorResponse(string $message, string $error, int $statusCode = 500): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'error' => $error,
        ], $statusCode);
    }
}
