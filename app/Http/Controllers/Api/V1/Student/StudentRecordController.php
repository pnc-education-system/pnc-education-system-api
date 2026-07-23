<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRecordRequest;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentRecordController extends Controller
{
    use ApiResponse, AuditableLogger;

    public function index(Student $student)
    {
        $records = $student->records()
            ->with('creator:id,name')
            ->orderByDesc('record_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Student records retrieved successfully',
            'data' => $records,
            'meta' => [
                'categories' => StudentRecord::CATEGORIES,
            ],
        ]);
    }

    public function store(StudentRecordRequest $request, Student $student)
    {
        $record = DB::transaction(function () use ($request, $student) {
            return $student->records()->create([
                ...$request->validated(),
                'created_by' => auth()->id(),
            ]);
        });

        $record->load('creator:id,name');
        $this->logAudit($record, 'student_record_created', $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Student record created successfully',
            'data' => $record,
        ], 201);
    }

    public function show(Student $student, int $record)
    {
        $record = StudentRecord::find($record);

        if (!$record) {
            return $this->error('Student record not found', 404);
        }

        if ($record->student_id !== $student->id) {
            return $this->error('Record not found for this student', 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Student record retrieved successfully',
            'data' => $record->load('creator:id,name'),
        ]);
    }

    public function update(StudentRecordRequest $request, Student $student, int $record)
    {
        $record = StudentRecord::find($record);

        if (!$record) {
            return $this->error('Student record not found', 404);
        }

        if ($record->student_id !== $student->id) {
            return $this->error('Record not found for this student', 404);
        }

        $oldValues = $record->only(array_keys($request->validated()));

        DB::transaction(function () use ($request, $record) {
            $record->update($request->validated());
        });

        $record = $record->fresh()->load('creator:id,name');
        $this->logAudit($record, 'student_record_updated', $request, $oldValues, $record->toArray());

        return response()->json([
            'status' => 'success',
            'message' => 'Student record updated successfully',
            'data' => $record,
        ]);
    }

    public function destroy(Request $request, Student $student, int $record)
    {
        $record = StudentRecord::find($record);

        if (!$record) {
            return $this->error('Student record not found', 404);
        }

        if ($record->student_id !== $student->id) {
            return $this->error('Record not found for this student', 404);
        }

        $oldValues = $record->toArray();

        DB::transaction(function () use ($record) {
            $record->delete();
        });

        $this->logAudit($record, 'student_record_deleted', $request, $oldValues);

        return response()->json([
            'status' => 'success',
            'message' => 'Student record deleted successfully',
        ]);
    }
}
