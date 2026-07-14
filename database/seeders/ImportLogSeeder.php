<?php

namespace Database\Seeders;

use App\Models\ImportLog;
use App\Models\ImportError;
use App\Models\User;
use Illuminate\Database\Seeder;

class ImportLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@pnc.edu.kh')->first();
        $staff = User::where('email', 'staff@pnc.edu.kh')->first();

        $adminId = $admin?->id ?? 1;
        $staffId = $staff?->id ?? 1;

        $logs = [
            [
                'file_name'     => 'enrollment_batch_a_2024.xlsx',
                'status'        => 'Completed',
                'total_rows'    => 150,
                'success_count' => 150,
                'error_count'   => 0,
                'imported_by'   => $adminId,
                'created_at'    => now()->subDays(10),
            ],
            [
                'file_name'     => 'enrollment_batch_b_2024.csv',
                'status'        => 'Completed',
                'total_rows'    => 200,
                'success_count' => 195,
                'error_count'   => 5,
                'imported_by'   => $staffId,
                'created_at'    => now()->subDays(7),
            ],
            [
                'file_name'     => 'summer_intake_2025.xlsx',
                'status'        => 'Completed',
                'total_rows'    => 320,
                'success_count' => 320,
                'error_count'   => 0,
                'imported_by'   => $adminId,
                'created_at'    => now()->subDays(5),
            ],
            [
                'file_name'     => 'student_update_2024.xlsx',
                'status'        => 'Failed',
                'total_rows'    => 45,
                'success_count' => 0,
                'error_count'   => 45,
                'imported_by'   => $staffId,
                'created_at'    => now()->subDays(3),
            ],
            [
                'file_name'     => 'enrollment_spring_2025.csv',
                'status'        => 'Processing',
                'total_rows'    => 500,
                'success_count' => 0,
                'error_count'   => 0,
                'imported_by'   => $adminId,
                'created_at'    => now()->subDay(),
            ],
        ];

        foreach ($logs as $logData) {
            $log = ImportLog::create($logData);

            // Add errors for logs that have error_count > 0
            if ($log->error_count > 0) {
                for ($i = 1; $i <= min($log->error_count, 5); $i++) {
                    ImportError::create([
                        'import_log_id' => $log->id,
                        'row_number'    => $i + 1,
                        'field'         => collect(['student_id', 'email', 'date_of_birth', 'program', 'phone'])->random(),
                        'error_message' => collect([
                            'Value is required and cannot be empty',
                            'Invalid date format, expected MM/DD/YYYY',
                            'Duplicate student ID found',
                            'Email format is invalid',
                            'Program code does not exist in system',
                        ])->random(),
                    ]);
                }
            }
        }
    }
}
