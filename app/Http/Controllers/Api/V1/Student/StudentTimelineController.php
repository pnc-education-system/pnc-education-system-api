<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\Student;
use App\Services\Student\StudentTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTimelineController extends Controller
{
    use ApiResponse;

    protected StudentTimelineService $timelineService;

    public function __construct(StudentTimelineService $timelineService)
    {
        $this->timelineService = $timelineService;
    }

    /**
     * Get student timeline
     * 
     * GET /api/v1/students/{studentId}/timeline
     */
    public function index(Request $request, int $studentId): JsonResponse
    {
        // Validate student exists
        $student = Student::find($studentId);
        
        if (!$student) {
            return $this->error('Student not found', 404);
        }

        // Get query parameters
        $search = $request->query('search');
        $category = $request->query('category');

        // Validate category if provided (case-insensitive)
        $validCategories = ['Enrollment', 'Document', 'ID Card', 'Status'];
        if ($category && !in_array(strtolower($category), array_map('strtolower', $validCategories))) {
            return $this->error('Invalid category. Valid categories are: ' . implode(', ', $validCategories), 400);
        }

        // Get timeline data
        $timeline = $this->timelineService->getTimeline($student, $search, $category);

        return response()->json([
            'status' => 'success',
            'message' => 'Timeline retrieved successfully',
            'data' => $timeline,
        ], 200);
    }
}
