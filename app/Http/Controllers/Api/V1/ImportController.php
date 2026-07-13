<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Imports\StudentsPreviewImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx|max:' . config('import.max_file_size_kb', 10240),
        ], [
            'file.required' => 'No file was uploaded. Please attach a .xlsx file to proceed.',
            'file.mimes'    => 'Invalid file format. Only .xlsx (Excel) files are accepted.',
            'file.max'      => 'File size exceeds the maximum allowed size of ' . (config('import.max_file_size_kb', 10240) / 1024) . ' MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first('file'),
            ], 422);
        }

        $file = $request->file('file');

        if ($file->getSize() === 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The uploaded file is empty. Please upload a file containing student data.',
            ], 422);
        }

        try {
            $rows = Excel::toArray(new StudentsPreviewImport, $file);
            $data = $rows[0] ?? [];

            if (empty($data)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The uploaded file contains no data rows. Please ensure your spreadsheet has at least one row of student data.',
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'total_rows' => count($data),
                    'columns'    => array_keys($data[0]),
                    'rows'       => $data,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The uploaded file could not be read. Please verify the file is a valid .xlsx format and try again.',
            ], 422);
        }
    }
}
