<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Http\Requests\StudentFilterRequest;
use App\Http\Requests\StudentStoreRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    use ApiResponse, AuditableLogger;

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

    public function store(StudentStoreRequest $request)
    {
        $student = Student::create([
            'student_id_no' => $request->student_id_no,
            'full_name' => $request->full_name,
            'gender' => $request->gender,
            'dob' => $request->dob,
            'phone' => $request->phone,
            'email' => $request->email,
            'province' => $request->province,
            'high_school' => $request->high_school,
            'selection_batch_id' => $request->selection_batch_id,
            'enrollment_status' => $request->enrollment_status,
            'intake_year' => $request->intake_year,
            'photo_path' => $request->photo_path,
            'created_by' => Auth::id(),
        ]);

        $this->logAudit($student, 'student_created', $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Student created successfully',
            'data' => new StudentResource($student->load('selectionBatch')),
        ], 201);
    }
}
