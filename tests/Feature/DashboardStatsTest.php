<?php

namespace Tests\Feature;

use App\Models\SelectionBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'      => 'Dashboard User',
            'email'     => 'dash@pnc.edu',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ], $overrides));
    }

    public function test_enrollment_dashboard_returns_accurate_counts(): void
    {
        $user  = $this->createUser();
        $token = JWTAuth::fromUser($user);

        $batchA = SelectionBatch::create([
            'name'  => 'Batch Alpha',
            'year'  => 2026,
        ]);

        $batchB = SelectionBatch::create([
            'name'  => 'Batch Beta',
            'year'  => 2026,
        ]);

        // Seed students across batches and statuses
        \App\Models\Student::create([
            'student_id_no'      => 'STU-001',
            'full_name'          => 'Alice',
            'gender'             => 'Female',
            'dob'                => '2000-01-01',
            'selection_batch_id' => $batchA->id,
            'enrollment_status'  => 'Pending',
            'intake_year'        => 2026,
            'created_by'         => $user->id,
        ]);

        \App\Models\Student::create([
            'student_id_no'      => 'STU-002',
            'full_name'          => 'Bob',
            'gender'             => 'Male',
            'dob'                => '2000-01-01',
            'selection_batch_id' => $batchA->id,
            'enrollment_status'  => 'Enrolled',
            'intake_year'        => 2026,
            'created_by'         => $user->id,
        ]);

        \App\Models\Student::create([
            'student_id_no'      => 'STU-003',
            'full_name'          => 'Charlie',
            'gender'             => 'Male',
            'dob'                => '2000-01-01',
            'selection_batch_id' => $batchA->id,
            'enrollment_status'  => 'Rejected',
            'intake_year'        => 2026,
            'created_by'         => $user->id,
        ]);

        \App\Models\Student::create([
            'student_id_no'      => 'STU-004',
            'full_name'          => 'Diana',
            'gender'             => 'Female',
            'dob'                => '2000-01-01',
            'selection_batch_id' => $batchB->id,
            'enrollment_status'  => 'Enrolled',
            'intake_year'        => 2026,
            'created_by'         => $user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/dashboard/enrollment');

        $response->assertStatus(200)
            ->assertJson([
                'total'    => 4,
                'pending'  => 1,
                'enrolled' => 2,
                'rejected' => 1,
                'rate'     => 50.0,
            ])
            ->assertJsonStructure([
                'total',
                'pending',
                'enrolled',
                'rejected',
                'rate',
                'by_batch' => [
                    '*' => [
                        'id',
                        'batch',
                        'year',
                        'total',
                        'pending',
                        'enrolled',
                        'rejected',
                    ],
                ],
            ]);

        $byBatch = $response->json('by_batch');
        $this->assertCount(2, $byBatch);

        $alpha = collect($byBatch)->firstWhere('batch', 'Batch Alpha');
        $this->assertNotNull($alpha);
        $this->assertSame(3, $alpha['total']);
        $this->assertSame(1, $alpha['pending']);
        $this->assertSame(1, $alpha['enrolled']);
        $this->assertSame(1, $alpha['rejected']);

        $beta = collect($byBatch)->firstWhere('batch', 'Batch Beta');
        $this->assertNotNull($beta);
        $this->assertSame(1, $beta['total']);
        $this->assertSame(0, $beta['pending']);
        $this->assertSame(1, $beta['enrolled']);
        $this->assertSame(0, $beta['rejected']);
    }

    public function test_enrollment_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/dashboard/enrollment');
        $response->assertStatus(401);
    }

    public function test_enrollment_dashboard_handles_empty_data(): void
    {
        $user  = $this->createUser();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/dashboard/enrollment');

        $response->assertStatus(200)
            ->assertJson([
                'total'    => 0,
                'pending'  => 0,
                'enrolled' => 0,
                'rejected' => 0,
                'rate'     => 0,
                'by_batch' => [],
            ]);
    }
}
