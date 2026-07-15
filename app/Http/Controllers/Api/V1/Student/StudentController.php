<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Requests\StudentFilterRequest;
use App\Http\Requests\UpdateStudentStatusRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use ApiResponse;

    public function index(StudentFilterRequest $request)
    {
        $query = Student::with('selectionBatch');

        $query->when($request->filled('status'), fn($q) => $q->where('enrollment_status', $request->status));

        $query->when($request->filled('batch'), fn($q) => $q->where('selection_batch_id', $request->batch));

        $query->when($request->filled('province'), fn($q) => $q->where('province', 'like', '%' . $request->province . '%'));

        $query->when($request->filled('search'), function ($q) use ($request) {
            $searchTerm = '%' . $request->search . '%';
            $q->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('student_id_no', 'like', $searchTerm)
                    ->orWhere('full_name', 'like', $searchTerm)
                    ->orWhere('phone', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm);
            });
        });

        $students = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'status' => 'success',
            'message' => 'Students retrieved successfully',
            'data' => StudentResource::collection($students),
            'pagination' => [
                'total' => $students->total(),
                'per_page' => $students->perPage(),
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'from' => $students->firstItem(),
                'to' => $students->lastItem(),
            ],
        ], 200);
    }

    public function updateStatus(UpdateStudentStatusRequest $request, $id)
    {
        $student = Student::find($id);

        if (!$student) {
            return $this->error('Student not found', 404);
        }

        $newStatus = $request->input('status');
        $note      = $request->input('note');

        if (!Student::isValidTransition($student->enrollment_status, $newStatus)) {
            $allowed = Student::validTransitionsFrom($student->enrollment_status);

            return $this->error(
                "Invalid status transition from '{$student->enrollment_status}'. " .
                (empty($allowed)
                    ? 'No further transitions are allowed from this status.'
                    : "Allowed transitions: " . implode(', ', $allowed) . '.'),
                422
            );
        }

        try {
            $history = $student->transitionStatus($newStatus, $note);

            return response()->json([
                'status'  => 'success',
                'message' => 'Student status updated successfully',
                'data'    => [
                    'student' => new StudentResource($student->fresh()->load('selectionBatch')),
                    'history' => [
                        'id'          => $history->id,
                        'old_status'  => $history->old_status,
                        'new_status'  => $history->new_status,
                        'note'        => $history->note,
                        'changed_by'  => $history->changed_by,
                        'created_at'  => $history->created_at,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return $this->error('Failed to update student status: ' . $e->getMessage(), 500);
        }
    }
}
