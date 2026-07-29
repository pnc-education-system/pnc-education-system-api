<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\Evaluation;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get dashboard aggregates including demographics, evaluation stats, and record summaries
     */
    public function aggregates(): JsonResponse
    {
        $demographics = $this->getDemographics();
        $cardStats = $this->getCardStats();
        $evaluationStats = $this->getEvaluationStats();
        $recordSummary = $this->getRecordSummary();
        $enrollmentFlow = $this->getEnrollmentFlow();
        $byBatch = $this->getEnrollmentByBatch();

        return response()->json([
            'status' => 'success',
            'message' => 'Dashboard aggregates retrieved successfully',
            'data' => [
                'demographics' => $demographics,
                'card_stats' => $cardStats,
                'evaluation_stats' => $evaluationStats,
                'record_summary' => $recordSummary,
                'enrollment_flow' => $enrollmentFlow,
                'by_batch' => $byBatch,
            ],
        ], 200);
    }

    /**
     * Get card generation statistics
     */
    private function getCardStats(): array
    {
        return [
            'total_cards' => StudentCard::count(),
        ];
    }

    /**
     * Get monthly enrollment flow — students submitted vs enrolled per month
     */
    private function getEnrollmentFlow(): array
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $submitted = array_fill(0, 12, 0);
        $enrolled = array_fill(0, 12, 0);

        $rows = Student::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as submitted'),
                DB::raw("SUM(CASE WHEN enrollment_status = 'Enrolled' THEN 1 ELSE 0 END) as enrolled")
            )
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy(DB::raw('MONTH(created_at)'))
            ->get();

        foreach ($rows as $row) {
            // MONTH() returns 1-12, array is 0-indexed
            $idx = (int) $row->month - 1;
            if ($idx >= 0 && $idx < 12) {
                $submitted[$idx] = (int) $row->submitted;
                $enrolled[$idx] = (int) $row->enrolled;
            }
        }

        return [
            'months' => $months,
            'submitted' => $submitted,
            'enrolled' => $enrolled,
        ];
    }

    /**
     * Get student count grouped by selection batch
     */
    private function getEnrollmentByBatch(): array
    {
        $batches = SelectionBatch::select(
                'selection_batches.id',
                'selection_batches.name',
                'selection_batches.year',
                DB::raw('COUNT(students.id) as count')
            )
            ->leftJoin('students', 'students.selection_batch_id', '=', 'selection_batches.id')
            ->groupBy('selection_batches.id', 'selection_batches.name', 'selection_batches.year')
            ->orderBy('selection_batches.year')
            ->orderBy('selection_batches.name')
            ->get();

        return $batches->map(function ($b) {
            return [
                'batch_name' => $b->name . ' (' . $b->year . ')',
                'year' => (int) $b->year,
                'count' => (int) $b->count,
            ];
        })->toArray();
    }

    /**
     * Get demographic statistics
     */
    private function getDemographics(): array
    {
        $totalStudents = Student::count();

        // By enrollment status
        $byStatus = Student::select('enrollment_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('enrollment_status')
            ->pluck('count', 'enrollment_status')
            ->toArray();

        // By gender
        $byGender = Student::select('gender')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

        // By province
        $byProvince = Student::select('province')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('province')
            ->groupBy('province')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'province')
            ->toArray();

        // By intake year
        $byIntakeYear = Student::select('intake_year')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('intake_year')
            ->groupBy('intake_year')
            ->orderByDesc('intake_year')
            ->pluck('count', 'intake_year')
            ->toArray();

        return [
            'total_students' => $totalStudents,
            'by_enrollment_status' => $byStatus,
            'by_gender' => $byGender,
            'by_province' => $byProvince,
            'by_intake_year' => $byIntakeYear,
        ];
    }

    /**
     * Get evaluation statistics
     */
    private function getEvaluationStats(): array
    {
        $totalEvaluations = Evaluation::count();

        // Average score
        $averageScore = Evaluation::avg('total_score') ?? 0;

        // By status
        $byStatus = Evaluation::select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // By evaluation period
        $byPeriod = Evaluation::select('evaluation_period')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('evaluation_period')
            ->groupBy('evaluation_period')
            ->orderByDesc('evaluation_period')
            ->limit(10)
            ->pluck('count', 'evaluation_period')
            ->toArray();

        // Students with evaluations
        $studentsWithEvaluations = Evaluation::distinct('student_id')->count('student_id');

        return [
            'total_evaluations' => $totalEvaluations,
            'average_score' => round((float) $averageScore, 2),
            'by_status' => $byStatus,
            'by_period' => $byPeriod,
            'students_with_evaluations' => $studentsWithEvaluations,
        ];
    }

    /**
     * Get record summary statistics
     */
    private function getRecordSummary(): array
    {
        $totalRecords = StudentRecord::count();

        // By category
        $byCategory = StudentRecord::select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        // Recent records (last 30 days)
        $recentRecords = StudentRecord::where('record_date', '>=', now()->subDays(30))
            ->count();

        // Students with records
        $studentsWithRecords = StudentRecord::distinct('student_id')->count('student_id');

        return [
            'total_records' => $totalRecords,
            'by_category' => $byCategory,
            'recent_records_30_days' => $recentRecords,
            'students_with_records' => $studentsWithRecords,
        ];
    }
}
