<?php

namespace App\Http\Controllers\Api\V1\SelectionBatch;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Models\SelectionBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SelectionBatchController extends Controller
{
    use ApiResponse, AuditableLogger;

    public function index(Request $request)
    {
        $query = SelectionBatch::withCount('students')->with('creator');

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        $batches = $query->orderBy('year', 'desc')->orderBy('name', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Selection batches retrieved successfully',
            'data' => $batches,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'year' => 'required|integer|digits:4',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $batch = SelectionBatch::create([
            'name' => $request->name,
            'year' => $request->year,
            'created_by' => auth()->id(),
        ]);

        $this->logAudit($batch, 'selection_batch_created', $request, [], $batch->toArray());

        return response()->json([
            'status' => 'success',
            'message' => 'Selection batch created successfully',
            'data' => $batch->loadCount('students')->load('creator'),
        ], 201);
    }

    public function show($id)
    {
        $batch = SelectionBatch::withCount('students')->with('creator')->find($id);

        if (!$batch) {
            return $this->error('Selection batch not found', 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Selection batch retrieved successfully',
            'data' => $batch,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $batch = SelectionBatch::find($id);

        if (!$batch) {
            return $this->error('Selection batch not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'year' => 'sometimes|required|integer|digits:4',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $oldValues = $batch->toArray();

        foreach (['name', 'year'] as $field) {
            if ($request->has($field)) {
                $batch->$field = $request->$field;
            }
        }

        $batch->save();
        $this->logAudit($batch, 'selection_batch_updated', $request, $oldValues, $batch->toArray());

        return response()->json([
            'status' => 'success',
            'message' => 'Selection batch updated successfully',
            'data' => $batch->loadCount('students')->load('creator'),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $batch = SelectionBatch::find($id);

        if (!$batch) {
            return $this->error('Selection batch not found', 404);
        }
        if ($batch->students()->count() > 0) {
            return $this->error('Cannot delete selection batch with associated students', 400);
        }

        $batchData = $batch->toArray();
        $batch->delete();
        $this->logAudit($batch, 'selection_batch_deleted', $request, $batchData, []);

        return response()->json([
            'status' => 'success',
            'message' => 'Selection batch deleted successfully',
        ], 200);
    }
}
