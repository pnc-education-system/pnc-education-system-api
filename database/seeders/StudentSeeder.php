<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\SelectionBatch;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $batch = SelectionBatch::firstOrCreate(
            ['name' => 'Batch 2024'],
            [
                'year' => 2024,
                'is_active' => true,
            ]
        );

        $students = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-15',
                'phone' => '0123456789',
                'email' => 'john.doe@example.com',
                'province' => 'Phnom Penh',
                'high_school' => 'Russey Keo High School',
                'selection_batch_id' => $batch->id,
                'enrollment_status' => 'Enrolled',
                'intake_year' => 2024,
                'created_by' => $user->id,
            ],
            [
                'student_id_no' => 'ST0002',
                'full_name' => 'Jane Smith',
                'gender' => 'Female',
                'dob' => '2000-03-22',
                'phone' => '0987654321',
                'email' => 'jane.smith@example.com',
                'province' => 'Siem Reap',
                'high_school' => 'Siem Reap High School',
                'selection_batch_id' => $batch->id,
                'enrollment_status' => 'Enrolled',
                'intake_year' => 2024,
                'created_by' => $user->id,
            ],
            [
                'student_id_no' => 'ST0003',
                'full_name' => 'Kim Sokha',
                'gender' => 'Male',
                'dob' => '1999-11-08',
                'phone' => '0855443322',
                'email' => 'kim.sokha@example.com',
                'province' => 'Battambang',
                'high_school' => 'Battambang High School',
                'selection_batch_id' => $batch->id,
                'enrollment_status' => 'Pending',
                'intake_year' => 2024,
                'created_by' => $user->id,
            ],
            [
                'student_id_no' => 'ST0004',
                'full_name' => 'Sophy Chea',
                'gender' => 'Female',
                'dob' => '2001-05-30',
                'phone' => '077889900',
                'email' => 'sophy.chea@example.com',
                'province' => 'Kampong Cham',
                'high_school' => 'Kampong Cham High School',
                'selection_batch_id' => $batch->id,
                'enrollment_status' => 'Enrolled',
                'intake_year' => 2024,
                'created_by' => $user->id,
            ],
            [
                'student_id_no' => 'ST0005',
                'full_name' => 'Vannak Vong',
                'gender' => 'Male',
                'dob' => '2000-07-12',
                'phone' => '060112233',
                'email' => 'vannak.vong@example.com',
                'province' => 'Takeo',
                'high_school' => 'Takeo High School',
                'selection_batch_id' => $batch->id,
                'enrollment_status' => 'Rejected',
                'intake_year' => 2024,
                'created_by' => $user->id,
            ],
        ];

        foreach ($students as $student) {
            Student::firstOrCreate(
                ['student_id_no' => $student['student_id_no']],
                $student
            );
        }
    }
}
