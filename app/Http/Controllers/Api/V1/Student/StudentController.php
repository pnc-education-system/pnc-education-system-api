<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Http\Requests\StudentFilterRequest;
use App\Http\Requests\StudentStoreRequest;
use App\Http\Requests\StudentUpdateRequest;
use App\Http\Requests\UpdateStudentStatusRequest;
use App\Http\Requests\BulkConfirmStudentsRequest;
use App\Http\Requests\BulkUpdateStudentStatusRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        $students = $query->orderBy('student_id_no', 'desc')->paginate(15);

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
            'enrolled_at' => $request->enrolled_at,
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

    public function show($id)
    {
        $student = Student::with('selectionBatch')->find($id);

        if (!$student) {
            return $this->error('Student not found', 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Student retrieved successfully',
            'data' => new StudentResource($student),
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

    public function update(StudentUpdateRequest $request, $id)
    {
        $student = Student::find($id);

        if (!$student) {
            return $this->error('Student not found', 404);
        }

        $data = $request->validated();
        unset($data['photo']);

        $newPhotoPath = null;
        $oldPhotoPath = $student->photo_path;

        try {
            if ($request->hasFile('photo')) {
                $newPhotoPath = $request->file('photo')->store('students/photos', 'public');
                $data['photo_path'] = $newPhotoPath;
            }

            $oldValues = $student->only(array_keys($data));

            DB::transaction(function () use ($student, $data) {
                $student->update($data);
            });

            if ($newPhotoPath && $oldPhotoPath) {
                $this->deleteStudentPhoto($oldPhotoPath);
            }

            $student = $student->fresh()->load('selectionBatch');
            $this->logAudit($student, 'student_updated', $request, $oldValues, $student->toArray());

            return response()->json([
                'status'  => 'success',
                'message' => 'Student updated successfully',
                'data'    => new StudentResource($student),
            ], 200);
        } catch (\Exception $e) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            return $this->error('Failed to update student: ' . $e->getMessage(), 500);
        }
    }

    private function deleteStudentPhoto(string $path): void
    {
        $photoPath = $this->normalizeStudentPhotoPath($path);

        if ($photoPath) {
            Storage::disk('public')->delete($photoPath);
        }
    }

    private function normalizeStudentPhotoPath(string $path): ?string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }

    public function bulkUpdateStatus(BulkUpdateStudentStatusRequest $request)
    {
        try {
            $studentIds = $request->input('student_ids');
            $newStatus = $request->input('status');
            $note = $request->input('note');

            $updatedStudents = [];

            DB::transaction(function () use ($studentIds, $newStatus, $note, &$updatedStudents) {
                $students = Student::whereIn('id', $studentIds)->get();

                foreach ($students as $student) {
                    // Update status directly (force transition)
                    $student->transitionStatus($newStatus, $note, Auth::id(), true);
                    $updatedStudents[] = $student->student_id_no;
                }
            });

            $updatedCount = count($updatedStudents);

            return response()->json([
                'status'  => 'success',
                'message' => "Successfully updated status for {$updatedCount} student(s).",
                'data'    => [
                    'updated_count' => $updatedCount,
                    'status' => $newStatus,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Bulk update error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to bulk update student status: ' . $e->getMessage(), 500);
        }
    }

    public function bulkConfirm(BulkConfirmStudentsRequest $request)
    {
        try {
            $studentIds = $request->input('student_ids');

            DB::transaction(function () use ($studentIds) {
                Student::whereIn('id', $studentIds)->update(['is_confirmed' => true]);
            });

            $confirmedCount = count($studentIds);

            return response()->json([
                'status'  => 'success',
                'message' => "Successfully confirmed {$confirmedCount} student(s).",
                'data'    => [
                    'confirmed_count' => $confirmedCount,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Bulk confirm error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to bulk confirm students: ' . $e->getMessage(), 500);
        }
    }
}
