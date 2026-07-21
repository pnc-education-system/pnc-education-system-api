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
use Illuminate\Support\Facades\Cache;
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

    public function show(Request $request, $id)
    {
        // Parse ?include= to request only needed relationships (comma-separated)
        // Default (no ?include) loads all for backward compatibility
        $allowedIncludes = ['records', 'evaluations', 'cards', 'histories', 'enrollment_status_histories'];
        $requestedIncludes = $request->filled('include')
            ? array_intersect(
                array_map('trim', explode(',', $request->input('include'))),
                $allowedIncludes
            )
            : $allowedIncludes;

        // Default limits with capping to prevent abuse
        $limits = [
            'records'     => min(max((int) $request->input('records_per_page', 10), 1), 50),
            'evaluations' => min(max((int) $request->input('evaluations_per_page', 10), 1), 50),
            'cards'       => min(max((int) $request->input('cards_per_page', 5), 1), 50),
            'histories'   => min(max((int) $request->input('histories_per_page', 10), 1), 50),
        ];

        // Build a unique cache key — includes a version counter so updates bust the cache
        $version = Cache::remember('student_version_' . $id, now()->addDays(1), fn () => 1);
        $cacheKey = 'student_detail_' . $id . '_v' . $version . '_' . md5(json_encode($requestedIncludes) . json_encode($limits));

        $student = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($id, $requestedIncludes, $limits) {
            $query = Student::query();

            // Always load the basic relationship
            $query->with('selectionBatch');

            // Conditionally load relationships with limits and descending order (newest first)
            if (in_array('records', $requestedIncludes)) {
                $query->with(['records' => function ($q) use ($limits) {
                    $q->orderBy('record_date', 'desc')
                      ->orderBy('created_at', 'desc')
                      ->take($limits['records']);
                }]);
                $query->with('records.attachments');
            }

            if (in_array('evaluations', $requestedIncludes)) {
                $query->with(['evaluations' => function ($q) use ($limits) {
                    $q->latest()->take($limits['evaluations']);
                }]);
                $query->with('evaluations.answers');
                $query->with('evaluations.evaluationForm');
            }

            if (in_array('cards', $requestedIncludes)) {
                $query->with(['cards' => function ($q) use ($limits) {
                    $q->latest()->take($limits['cards']);
                }]);
            }

            if (in_array('histories', $requestedIncludes) || in_array('enrollment_status_histories', $requestedIncludes)) {
                $query->with(['enrollmentStatusHistories' => function ($q) use ($limits) {
                    $q->latest()->take($limits['histories']);
                }]);
            }

            return $query->find($id);
        });

        if (!$student) {
            return $this->error('Student not found', 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Student retrieved successfully',
            'data'    => new StudentResource($student),
            'meta'    => [
                'includes' => $requestedIncludes,
                'limits'   => $limits,
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

            $this->clearStudentCache($student->id);

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

            $this->clearStudentCache($student->id);

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

            // Clear cache for all updated students
            foreach ($studentIds as $studentId) {
                $this->clearStudentCache($studentId);
            }

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

            // Clear cache for all confirmed students
            foreach ($studentIds as $studentId) {
                $this->clearStudentCache($studentId);
            }

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

    /**
     * Bust all cached student detail responses for the given student ID.
     *
     * Increments a version counter whose value is embedded in every detail cache key.
     * The next request to the show() endpoint will compute a new (higher) version,
     * causing a cache miss and re-fetching fresh data from the database.
     *
     * Works with any cache driver (database, file, redis, memcached, etc.).
     */
    private function clearStudentCache(int $studentId): void
    {
        Cache::increment('student_version_' . $studentId);
    }
}
