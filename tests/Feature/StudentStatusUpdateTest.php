<?php

namespace Tests\Feature;

use App\Models\EnrollmentStatusHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        // Create role with enrollment.manage permission
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'Manage Enrollment', 'slug' => 'enrollment.manage', 'module' => 'enrollment']);
        $role->permissions()->sync([$perm->id]);

        $this->user = User::create([
            'name'      => 'Admin',
            'email'     => 'admin@test.com',
            'password'  => Hash::make('password'),
            'role_id'   => $role->id,
            'is_active' => true,
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 2025', 'year' => 2025]);

        $this->student = Student::create([
            'student_id_no'     => 'ST001',
            'full_name'         => 'Test Student',
            'gender'            => 'Male',
            'dob'               => '2000-01-01',
            'selection_batch_id'=> 1,
            'enrollment_status' => Student::STATUS_PENDING,
            'intake_year'       => 2025,
            'created_by'        => $this->user->id,
        ]);
    }

    private function assertStatusUpdated(Student $student, string $expectedStatus): void
    {
        $student->refresh();
        $this->assertEquals($expectedStatus, $student->enrollment_status);
    }

    private function assertHistoryRecorded(
        int $studentId,
        string $oldStatus,
        string $newStatus,
        ?string $expectedNote,
        int $changedBy
    ): void {
        $history = EnrollmentStatusHistory::where('student_id', $studentId)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($history, 'Expected a history record to exist');
        $this->assertEquals($oldStatus, $history->old_status);
        $this->assertEquals($newStatus, $history->new_status);
        $this->assertEquals($expectedNote, $history->note);
        $this->assertEquals($changedBy, $history->changed_by);
    }

    // ----------------------------------------------------------------
    //  Valid transitions
    // ----------------------------------------------------------------

    public function test_valid_transition_pending_to_enrolled_with_note(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_ENROLLED,
                'note'   => 'Approved for enrollment',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Student status updated successfully')
            ->assertJsonPath('data.student.enrollment_status', Student::STATUS_ENROLLED)
            ->assertJsonPath('data.history.old_status', Student::STATUS_PENDING)
            ->assertJsonPath('data.history.new_status', Student::STATUS_ENROLLED)
            ->assertJsonPath('data.history.note', 'Approved for enrollment')
            ->assertJsonPath('data.history.changed_by', $this->user->id)
            ->assertJsonStructure([
                'data' => [
                    'history' => ['id', 'old_status', 'new_status', 'note', 'changed_by', 'created_at'],
                ],
            ]);

        $this->assertStatusUpdated($this->student, Student::STATUS_ENROLLED);
        $this->assertHistoryRecorded(
            $this->student->id,
            Student::STATUS_PENDING,
            Student::STATUS_ENROLLED,
            'Approved for enrollment',
            $this->user->id
        );
    }

    public function test_valid_transition_without_note(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_DROPPED,
            ]);

        $response->assertStatus(200);
        $this->assertStatusUpdated($this->student, Student::STATUS_DROPPED);

        $history = EnrollmentStatusHistory::where('student_id', $this->student->id)->first();
        $this->assertNull($history->note);
    }

    public function test_chain_transition_pending_to_enrolled_to_graduated(): void
    {
        // Step 1: Pending → Enrolled
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_ENROLLED,
            ])->assertStatus(200);

        $this->assertStatusUpdated($this->student, Student::STATUS_ENROLLED);

        // Step 2: Enrolled → Graduated
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_GRADUATED,
                'note'   => 'Completed all requirements',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.student.enrollment_status', Student::STATUS_GRADUATED)
            ->assertJsonPath('data.history.old_status', Student::STATUS_ENROLLED)
            ->assertJsonPath('data.history.new_status', Student::STATUS_GRADUATED)
            ->assertJsonPath('data.history.note', 'Completed all requirements');

        $this->assertStatusUpdated($this->student, Student::STATUS_GRADUATED);
        $this->assertEquals(2, EnrollmentStatusHistory::where('student_id', $this->student->id)->count());
    }

    // ----------------------------------------------------------------
    //  Invalid transitions
    // ----------------------------------------------------------------

    public function test_invalid_transition_from_terminal_state_graduated(): void
    {
        // Set student to Graduated (terminal state)
        $this->student->update(['enrollment_status' => Student::STATUS_GRADUATED]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_ENROLLED,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422)
            ->assertJsonPath('error.message', "Invalid status transition from 'Graduated'. No further transitions are allowed from this status.");
    }

    public function test_invalid_transition_skip_step_pending_to_graduated(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_GRADUATED,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422)
            ->assertJsonPath('error.message', "Invalid status transition from 'Pending'. Allowed transitions: Enrolled, Rejected, Dropped.");
    }

    public function test_invalid_transition_same_status(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_PENDING,
            ]);

        $response->assertStatus(422);
    }

    // ----------------------------------------------------------------
    //  Student not found
    // ----------------------------------------------------------------

    public function test_student_not_found(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson('/api/v1/students/99999/status', [
                'status' => Student::STATUS_ENROLLED,
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 404)
            ->assertJsonPath('error.message', 'Student not found');
    }

    // ----------------------------------------------------------------
    //  Authorization & permission checks
    // ----------------------------------------------------------------

    public function test_requires_authentication(): void
    {
        $response = $this->patchJson("/api/v1/students/{$this->student->id}/status", [
            'status' => Student::STATUS_ENROLLED,
        ]);

        $response->assertStatus(401);
    }

    public function test_requires_enrollment_manage_permission(): void
    {
        // Create a user with a different permission (not enrollment.manage)
        $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $perm = Permission::create(['name' => 'View Students', 'slug' => 'students.view', 'module' => 'students']);
        $role->permissions()->sync([$perm->id]);

        $viewer = User::create([
            'name'      => 'Viewer',
            'email'     => 'viewer@test.com',
            'password'  => Hash::make('password'),
            'role_id'   => $role->id,
            'is_active' => true,
        ]);
        $viewerToken = JWTAuth::fromUser($viewer);

        $response = $this->withHeader('Authorization', "Bearer {$viewerToken}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_ENROLLED,
            ]);

        $response->assertStatus(403);
    }

    // ----------------------------------------------------------------
    //  Validation errors
    // ----------------------------------------------------------------

    public function test_validation_requires_status_field(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422)
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_validation_rejects_invalid_status_value(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => 'InvalidStatus',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422)
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }

    // ----------------------------------------------------------------
    //  History integrity
    // ----------------------------------------------------------------

    public function test_history_is_recorded_with_correct_timestamp(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_REJECTED,
                'note'   => 'Did not meet criteria',
            ])->assertStatus(200);

        $history = EnrollmentStatusHistory::where('student_id', $this->student->id)->first();

        $this->assertNotNull($history->created_at);
        $this->assertEquals($this->user->id, $history->changed_by);
        $this->assertEquals(Student::STATUS_PENDING, $history->old_status);
        $this->assertEquals(Student::STATUS_REJECTED, $history->new_status);
        $this->assertEquals('Did not meet criteria', $history->note);
    }

    public function test_multiple_status_updates_record_separate_history_entries(): void
    {
        // Trigger 3 valid transitions: Pending → Enrolled → Dropped → Pending
        $transitions = [
            ['status' => Student::STATUS_ENROLLED, 'note' => 'Step 1: Enrolled'],
            ['status' => Student::STATUS_DROPPED,  'note' => 'Step 2: Dropped out'],
            ['status' => Student::STATUS_PENDING,  'note' => 'Step 3: Re-applied'],
        ];

        foreach ($transitions as $i => $step) {
            $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                ->patchJson("/api/v1/students/{$this->student->id}/status", [
                    'status' => $step['status'],
                    'note'   => $step['note'],
                ]);

            $response->assertStatus(200);
        }

        $histories = EnrollmentStatusHistory::where('student_id', $this->student->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $histories);

        $this->assertEquals(Student::STATUS_PENDING,  $histories[0]->old_status);
        $this->assertEquals(Student::STATUS_ENROLLED, $histories[0]->new_status);
        $this->assertEquals('Step 1: Enrolled',       $histories[0]->note);

        $this->assertEquals(Student::STATUS_ENROLLED, $histories[1]->old_status);
        $this->assertEquals(Student::STATUS_DROPPED,  $histories[1]->new_status);
        $this->assertEquals('Step 2: Dropped out',    $histories[1]->note);

        $this->assertEquals(Student::STATUS_DROPPED,  $histories[2]->old_status);
        $this->assertEquals(Student::STATUS_PENDING,  $histories[2]->new_status);
        $this->assertEquals('Step 3: Re-applied',     $histories[2]->note);

        // All history entries should reference the same user
        foreach ($histories as $h) {
            $this->assertEquals($this->user->id, $h->changed_by);
        }
    }

    // ----------------------------------------------------------------
    //  Audit record tests
    // ----------------------------------------------------------------

    public function test_status_change_writes_audit_record(): void
    {
        $initialHistoryCount = EnrollmentStatusHistory::where('student_id', $this->student->id)->count();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_ENROLLED,
                'note'   => 'Approved for enrollment',
            ]);

        $response->assertStatus(200);

        // Verify audit record was created
        $finalHistoryCount = EnrollmentStatusHistory::where('student_id', $this->student->id)->count();
        $this->assertEquals($initialHistoryCount + 1, $finalHistoryCount);

        // Verify audit record details
        $auditRecord = EnrollmentStatusHistory::where('student_id', $this->student->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($auditRecord);
        $this->assertEquals($this->student->id, $auditRecord->student_id);
        $this->assertEquals(Student::STATUS_PENDING, $auditRecord->old_status);
        $this->assertEquals(Student::STATUS_ENROLLED, $auditRecord->new_status);
        $this->assertEquals('Approved for enrollment', $auditRecord->note);
        $this->assertEquals($this->user->id, $auditRecord->changed_by);
        $this->assertNotNull($auditRecord->created_at);
    }

    public function test_audit_record_includes_user_and_timestamp(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_REJECTED,
                'note'   => 'Application rejected',
            ])->assertStatus(200);

        $auditRecord = EnrollmentStatusHistory::where('student_id', $this->student->id)
            ->latest('created_at')
            ->first();

        // Verify user who made the change is recorded
        $this->assertEquals($this->user->id, $auditRecord->changed_by);

        // Verify timestamp is recorded and is recent
        $this->assertNotNull($auditRecord->created_at);
        $this->assertLessThan(5, now()->diffInSeconds($auditRecord->created_at));
    }

    // ----------------------------------------------------------------
    //  Dashboard reflection tests
    // ----------------------------------------------------------------

    public function test_dashboard_reflects_change_immediately_after_status_update(): void
    {
        // Get initial student count by status
        $initialPendingCount = Student::where('enrollment_status', Student::STATUS_PENDING)->count();
        $initialEnrolledCount = Student::where('enrollment_status', Student::STATUS_ENROLLED)->count();

        // Update student status
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_ENROLLED,
            ])->assertStatus(200);

        // Verify dashboard reflects the change immediately
        $finalPendingCount = Student::where('enrollment_status', Student::STATUS_PENDING)->count();
        $finalEnrolledCount = Student::where('enrollment_status', Student::STATUS_ENROLLED)->count();

        $this->assertEquals($initialPendingCount - 1, $finalPendingCount);
        $this->assertEquals($initialEnrolledCount + 1, $finalEnrolledCount);

        // Verify the student's current status is reflected correctly
        $this->student->refresh();
        $this->assertEquals(Student::STATUS_ENROLLED, $this->student->enrollment_status);
    }

    public function test_dashboard_api_returns_updated_student_immediately(): void
    {
        // Update student status
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/students/{$this->student->id}/status", [
                'status' => Student::STATUS_ENROLLED,
                'note'   => 'Status updated',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.student.enrollment_status', Student::STATUS_ENROLLED);

        // Immediately fetch the student via API to verify dashboard reflects change
        $getResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/students/{$this->student->id}");

        $getResponse->assertStatus(200)
            ->assertJsonPath('data.enrollment_status', Student::STATUS_ENROLLED);
    }
}
