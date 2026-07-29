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
use App\Http\Requests\StoreStudentPhotoRequest;
use App\Http\Resources\StudentResource;
use App\Http\Resources\EvaluationHistoryResource;
use App\Models\Student;
use App\Models\Evaluation;
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
        // Auto-set enrolled_at to current time if status is Enrolled and no time was provided
        $enrolledAt = $request->enrolled_at;
        if ($request->enrollment_status === Student::STATUS_ENROLLED && empty($enrolledAt)) {
            $enrolledAt = now();
        }

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
            'enrolled_at' => $enrolledAt,
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

    public function history($id)
    {
        $student = Student::find($id);

        if (!$student) {
            return $this->error('Student not found', 404);
        }

        // 1. Status changes
        $statusHistories = \App\Models\EnrollmentStatusHistory::where('student_id', $id)
            ->with('changedBy')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'status_' . $item->id,
                    'type' => 'status_change',
                    'title' => 'Status updated to ' . $item->new_status,
                    'description' => 'Changed from ' . $item->old_status . ($item->note ? '. Note: ' . $item->note : ''),
                    'note' => $item->note,
                    'performed_by' => $item->changedBy?->name ?? 'System',
                    'date' => $item->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        // 2. Profile updates (AuditLog)
        $auditLogs = \App\Models\AuditLog::where('auditable_type', Student::class)
            ->where('auditable_id', $id)
            ->where('event', 'student_updated')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                $descriptionParts = [];
                if (!empty($item->old_values) && !empty($item->new_values)) {
                    foreach ($item->old_values as $key => $oldVal) {
                        $newVal = $item->new_values[$key] ?? null;
                        if ($oldVal !== $newVal && $key !== 'updated_at') {
                            $readableKey = ucfirst(str_replace('_', ' ', $key));
                            $descriptionParts[] = "$readableKey changed from '" . ($oldVal ?? 'none') . "' to '" . ($newVal ?? 'none') . "'";
                        }
                    }
                }
                $description = empty($descriptionParts)
                    ? 'Student profile updated.'
                    : implode(', ', $descriptionParts);

                return [
                    'id' => 'audit_' . $item->id,
                    'type' => 'record_update',
                    'title' => 'Profile details updated',
                    'description' => $description,
                    'performed_by' => $item->user?->name ?? 'System',
                    'date' => $item->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        // 3. Evaluations
        $evaluations = \App\Models\Evaluation::where('student_id', $id)
            ->with(['reviewer', 'evaluationForm'])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($item) {
                $formTitle = $item->evaluationForm?->title ?? 'Student Evaluation';
                return [
                    'id' => 'eval_' . $item->id,
                    'type' => 'evaluation',
                    'title' => "Evaluation: $formTitle",
                    'description' => "Period: {$item->evaluation_period}, Score: {$item->total_score}, Status: {$item->status}",
                    'performed_by' => $item->reviewer?->name ?? 'System',
                    'date' => ($item->submitted_at ?? $item->created_at)?->format('Y-m-d H:i:s'),
                ];
            });

        // 4. Incident/Journal records (StudentRecords)
        $records = \App\Models\StudentRecord::where('student_id', $id)
            ->with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'record_' . $item->id,
                    'type' => 'record_update',
                    'title' => $item->title ?? ('Journal Record: ' . ucfirst($item->category ?? 'general')),
                    'description' => $item->description ?? '',
                    'performed_by' => $item->creator?->name ?? 'System',
                    'date' => ($item->record_date ?? $item->created_at)?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                ];
            });

        // Merge all and sort by date descending
        $history = $statusHistories
            ->concat($auditLogs)
            ->concat($evaluations)
            ->concat($records)
            ->sortByDesc('date')
            ->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Student history retrieved successfully',
            'data' => $history,
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

    public function uploadPhoto(StoreStudentPhotoRequest $request, $id)
    {
        $student = Student::find($id);

        if (!$student) {
            return $this->error('Student not found', 404);
        }

        $oldPhotoPath = $student->photo_path;

        try {
            $newPhotoPath = $request->file('photo')->store('students/photos', 'public');

            $student->update(['photo_path' => $newPhotoPath]);

            if ($oldPhotoPath) {
                $this->deleteStudentPhoto($oldPhotoPath);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Student photo uploaded successfully',
                'data'    => [
                    'photo_url' => url("storage/{$newPhotoPath}"),
                ],
            ], 200);
        } catch (\Exception $e) {
            if (isset($newPhotoPath)) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            return $this->error('Failed to upload photo: ' . $e->getMessage(), 500);
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

    /**
     * Serve a student's photo through Laravel middleware stack.
     * This ensures CORS headers are applied (via HandleCors middleware).
     */
    public function servePhoto($id)
    {
        $student = Student::find($id);

        if (!$student || !$student->photo_path) {
            return $this->error('Photo not found', 404);
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($student->photo_path)) {
            return $this->error('Photo file not found', 404);
        }

        $absolutePath = $disk->path($student->photo_path);
        $mimeType = $disk->mimeType($student->photo_path) ?: 'image/jpeg';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Get evaluation history with period-over-period comparison
     * 
     * @param int $id Student ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function evaluationHistory($id)
    {
        $student = Student::find($id);

        if (!$student) {
            return $this->error('Student not found', 404);
        }

        $evaluations = Evaluation::where('student_id', $id)
            ->with(['answers.question.category', 'reviewer', 'evaluationForm'])
            ->orderBy('submitted_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Calculate period-over-period comparisons (oldest to newest)
        $previousEvaluation = null;
        $evaluationsData = [];

        foreach ($evaluations as $index => $evaluation) {
            $periodOverPeriod = null;

            if ($previousEvaluation) {
                $previousScore = (float) $previousEvaluation->total_score;
                $currentScore = (float) $evaluation->total_score;
                $change = $currentScore - $previousScore;
                $changePercent = $previousScore > 0 
                    ? round(($change / $previousScore) * 100, 2) 
                    : 0;

                $periodOverPeriod = [
                    'previous_total' => $previousScore,
                    'change' => $change >= 0 ? "+{$change}" : (string) $change,
                    'change_percent' => $changePercent >= 0 ? "+{$changePercent}%" : "{$changePercent}%",
                ];
            }

            $evaluationsData[] = [
                'evaluation' => $evaluation,
                'period_over_period' => $periodOverPeriod,
            ];
            
            $previousEvaluation = $evaluation;
        }

        // Reverse to show newest first in API response
        $evaluationsData = array_reverse($evaluationsData);

        // Calculate trend summary
        $trendSummary = $this->calculateTrendSummary($evaluations);

        // Build response with period_over_period included
        $evaluationsResponse = collect($evaluationsData)->map(function ($item) {
            $evaluation = $item['evaluation'];
            return [
                'id' => $evaluation->id,
                'student_id' => $evaluation->student_id,
                'evaluation_form_id' => $evaluation->evaluation_form_id,
                'evaluation_period' => $evaluation->evaluation_period,
                'total_score' => (float) $evaluation->total_score,
                'status' => $evaluation->status,
                'submitted_at' => $evaluation->submitted_at?->format('Y-m-d H:i:s'),
                'reviewed_by' => $evaluation->reviewed_by,
                'reviewer_name' => $evaluation->reviewer?->name,
                'evaluation_form' => [
                    'id' => $evaluation->evaluationForm->id,
                    'name' => $evaluation->evaluationForm->name,
                ],
                'period_over_period' => $item['period_over_period'],
                'created_at' => $evaluation->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $evaluation->updated_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Evaluation history retrieved successfully',
            'data' => [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'evaluations' => $evaluationsResponse,
                'trend_summary' => $trendSummary,
            ],
        ], 200);
    }

    /**
     * Calculate trend summary statistics
     */
    private function calculateTrendSummary($evaluations): array
    {
        if ($evaluations->isEmpty()) {
            return [
                'average_score' => 0,
                'improvement_rate' => '0%',
                'best_period' => null,
                'total_evaluations' => 0,
            ];
        }

        $totalScore = 0;
        $bestScore = 0;
        $bestPeriod = null;

        foreach ($evaluations as $evaluation) {
            $score = (float) $evaluation->total_score;
            $totalScore += $score;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPeriod = $evaluation->evaluation_period;
            }
        }

        $averageScore = round($totalScore / $evaluations->count(), 2);
        
        // Calculate improvement rate (oldest vs newest)
        $improvementRate = '0%';
        if ($evaluations->count() > 1) {
            // Since evaluations are ordered ASC (oldest first), first() is oldest, last() is newest
            $oldestScore = (float) $evaluations->first()->total_score;
            $newestScore = (float) $evaluations->last()->total_score;
            $change = $newestScore - $oldestScore;
            $rate = $oldestScore > 0 ? round(($change / $oldestScore) * 100, 2) : 0;
            $improvementRate = $rate >= 0 ? "+{$rate}%" : "{$rate}%";
        }

        return [
            'average_score' => $averageScore,
            'improvement_rate' => $improvementRate,
            'best_period' => $bestPeriod,
            'best_score' => $bestScore,
            'total_evaluations' => $evaluations->count(),
        ];
    }
}
