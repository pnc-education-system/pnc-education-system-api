<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\Evaluation;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get dashboard aggregates including demographics, evaluation stats, and record summaries
     */
    public function aggregates(): JsonResponse
    {
        $demographics = $this->getDemographics();
        $evaluationStats = $this->getEvaluationStats();
        $recordSummary = $this->getRecordSummary();

        return response()->json([
            'status' => 'success',
            'message' => 'Dashboard aggregates retrieved successfully',
            'data' => [
                'demographics' => $demographics,
                'evaluation_stats' => $evaluationStats,
                'record_summary' => $recordSummary,
            ],
        ], 200);
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
