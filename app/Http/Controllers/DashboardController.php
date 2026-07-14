<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Models\SelectionBatch;

class DashboardController extends Controller
{
    public function enrollmentStats()
    {
        $total = Student::count();
        $pending = Student::where('enrollment_status', 'Pending')->count();
        $enrolled = Student::where('enrollment_status', 'Enrolled')->count();
        $rejected = Student::where('enrollment_status', 'Rejected')->count();

        $rate = $total > 0 ? round(($enrolled / $total) * 100, 2) : 0;

        $byBatch = SelectionBatch::query()
            ->leftJoin('students', 'students.selection_batch_id', '=', 'selection_batches.id')
            ->select(
                'selection_batches.id',
                'selection_batches.name as batch',
                'selection_batches.year',
                DB::raw('COUNT(students.id) as total'),
                DB::raw('SUM(CASE WHEN students.enrollment_status = "Pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN students.enrollment_status = "Enrolled" THEN 1 ELSE 0 END) as enrolled'),
                DB::raw('SUM(CASE WHEN students.enrollment_status = "Rejected" THEN 1 ELSE 0 END) as rejected'),
            )
            ->groupBy('selection_batches.id', 'selection_batches.name', 'selection_batches.year')
            ->get();

        return response()->json([
            'total' => $total,
            'pending' => $pending,
            'enrolled' => $enrolled,
            'rejected' => $rejected,
            'rate' => $rate,
            'by_batch' => $byBatch,
        ]);
    }
}
