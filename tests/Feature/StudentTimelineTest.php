<?php

namespace Tests\Feature;

use App\Models\CardTemplate;
use App\Models\EnrollmentStatusHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentTimelineTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;
    private Student $student;
    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        // Create role with students.view permission (sufficient for timeline access)
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create([
            'name' => 'View Students',
            'slug' => 'students.view',
            'module' => 'students',
        ]);
        $role->permissions()->sync([$perm->id]);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        // Create a selection batch
        SelectionBatch::create(['id' => 1, 'name' => 'Batch 2025', 'year' => 2025]);

        // Create a student
        $this->student = Student::create([
            'student_id_no' => 'ST001',
            'full_name' => 'Test Student',
            'gender' => 'Male',
            'dob' => '2000-01-01',
            'selection_batch_id' => 1,
            'enrollment_status' => Student::STATUS_PENDING,
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ]);

        $this->baseUrl = "/api/v1/students/{$this->student->id}/timeline";
    }

    /**
     * Helper to create a default card template (needed by StudentCard foreign key).
     */
    private function createCardTemplate(): CardTemplate
    {
        return CardTemplate::create([
            'name' => 'Default Template',
            'layout_json' => '{}',
            'is_default' => true,
        ]);
    }

    // ----------------------------------------------------------------
    //  Authentication & Authorization
    // ----------------------------------------------------------------

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(401);
    }

    public function test_requires_permission(): void
    {
        // Create a user with no permissions
        $role = Role::create(['name' => 'Guest', 'slug' => 'guest']);
        $guest = User::create([
            'name' => 'Guest',
            'email' => 'guest@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $guestToken = JWTAuth::fromUser($guest);

        $response = $this->withHeader('Authorization', "Bearer {$guestToken}")
            ->getJson($this->baseUrl);

        $response->assertStatus(403);
    }

    // ----------------------------------------------------------------
    //  Student Not Found
    // ----------------------------------------------------------------

    public function test_student_not_found_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/students/999999/timeline');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 404)
            ->assertJsonPath('error.message', 'Student not found');
    }

    // ----------------------------------------------------------------
    //  Empty Timeline – Student Created Event
    // ----------------------------------------------------------------

    public function test_empty_timeline_returns_student_created_event(): void
    {
        // Student has no enrollment history, cards, records, or attachments
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Timeline retrieved successfully')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'category', 'title', 'description', 'created_at'],
                ],
            ]);

        // Exactly 1 event: "Student Created"
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.category', 'Status')
            ->assertJsonPath('data.0.title', 'Student Created')
            ->assertJsonPath('data.0.description', 'Student profile created');
    }

    // ----------------------------------------------------------------
    //  Full Timeline – Enrollment & ID Card Events
    // ----------------------------------------------------------------

    public function test_timeline_includes_enrollment_history(): void
    {
        // Create enrollment status history entries
        EnrollmentStatusHistory::create([
            'student_id' => $this->student->id,
            'old_status' => Student::STATUS_PENDING,
            'new_status' => Student::STATUS_ENROLLED,
            'graduated_status' => Student::STATUS_ENROLLED,
            'note' => 'Approved for enrollment',
            'changed_by' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        EnrollmentStatusHistory::create([
            'student_id' => $this->student->id,
            'old_status' => Student::STATUS_ENROLLED,
            'new_status' => Student::STATUS_GRADUATED,
            'graduated_status' => Student::STATUS_GRADUATED,
            'note' => 'Completed all requirements',
            'changed_by' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl);

        $response->assertStatus(200);

        $enrollmentEvents = collect($response->json('data'))
            ->filter(fn ($e) => $e['category'] === 'Enrollment');

        $this->assertCount(2, $enrollmentEvents);
        $this->assertEquals('Status Changed', $enrollmentEvents->first()['title']);
    }

    public function test_timeline_includes_id_card_events(): void
    {
        // Card template must exist before student card (foreign key constraint)
        $this->createCardTemplate();

        StudentCard::create([
            'student_id' => $this->student->id,
            'template_id' => 1,
            'card_number' => 'CARD-001',
            'qr_token' => 'qr-001',
            'issued_at' => now()->subDays(5),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl);

        $response->assertStatus(200);

        $cardEvents = collect($response->json('data'))
            ->filter(fn ($e) => $e['category'] === 'ID Card');

        $this->assertCount(1, $cardEvents);
        $this->assertEquals('ID Card Issued', $cardEvents->first()['title']);
        $this->assertStringContainsString('CARD-001', $cardEvents->first()['description']);
    }

    // ----------------------------------------------------------------
    //  Sorting – Newest First
    // ----------------------------------------------------------------

    public function test_timeline_sorted_newest_first(): void
    {
        // Create events with different dates
        EnrollmentStatusHistory::create([
            'student_id' => $this->student->id,
            'old_status' => Student::STATUS_PENDING,
            'new_status' => Student::STATUS_ENROLLED,
            'graduated_status' => Student::STATUS_ENROLLED,
            'changed_by' => $this->user->id,
            'created_at' => now()->subDays(10),
        ]);

        $this->createCardTemplate();

        StudentCard::create([
            'student_id' => $this->student->id,
            'template_id' => 1,
            'card_number' => 'CARD-002',
            'qr_token' => 'qr-002',
            'issued_at' => now()->subDays(1),
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl);

        $response->assertStatus(200);

        $data = $response->json('data');

        // Extract created_at values
        $dates = collect($data)->pluck('created_at')->toArray();

        // Verify sorted descending (newest first)
        for ($i = 0; $i < count($dates) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $dates[$i + 1],
                $dates[$i],
                'Timeline is not sorted newest first'
            );
        }
    }

    // ----------------------------------------------------------------
    //  Search
    // ----------------------------------------------------------------

    public function test_search_filters_by_keyword(): void
    {
        EnrollmentStatusHistory::create([
            'student_id' => $this->student->id,
            'old_status' => Student::STATUS_PENDING,
            'new_status' => Student::STATUS_ENROLLED,
            'graduated_status' => Student::STATUS_ENROLLED,
            'note' => 'Approved for enrollment',
            'changed_by' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        // Search for "Approved" (appears in the enrollment event description)
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?search=Approved');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should include the enrollment event (description contains "Approved")
        $this->assertGreaterThan(0, count($data));
        foreach ($data as $entry) {
            $found = stripos($entry['title'], 'Approved') !== false ||
                     stripos($entry['description'], 'Approved') !== false ||
                     stripos($entry['category'], 'Approved') !== false;
            $this->assertTrue($found, "Entry does not match search: " . json_encode($entry));
        }
    }

    public function test_search_is_case_insensitive(): void
    {
        EnrollmentStatusHistory::create([
            'student_id' => $this->student->id,
            'old_status' => Student::STATUS_PENDING,
            'new_status' => Student::STATUS_ENROLLED,
            'graduated_status' => Student::STATUS_ENROLLED,
            'note' => 'Approved for enrollment',
            'changed_by' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        // Search with lowercase
        $responseLower = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?search=approved');

        // Search with uppercase
        $responseUpper = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?search=APPROVED');

        // Search with mixed case
        $responseMixed = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?search=ApPrOvEd');

        $responseLower->assertStatus(200);
        $responseUpper->assertStatus(200);
        $responseMixed->assertStatus(200);

        $this->assertCount(
            count($responseLower->json('data')),
            $responseUpper->json('data'),
            'Lowercase and uppercase search returned different counts'
        );

        $this->assertCount(
            count($responseLower->json('data')),
            $responseMixed->json('data'),
            'Lowercase and mixed-case search returned different counts'
        );

        // Each result should contain the keyword
        foreach ($responseLower->json('data') as $entry) {
            $this->assertStringContainsStringIgnoringCase('approved', $entry['description']);
        }
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?search=NonExistentXYZ');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    // ----------------------------------------------------------------
    //  Category Filtering
    // ----------------------------------------------------------------

    public function test_filter_by_category(): void
    {
        EnrollmentStatusHistory::create([
            'student_id' => $this->student->id,
            'old_status' => Student::STATUS_PENDING,
            'new_status' => Student::STATUS_ENROLLED,
            'graduated_status' => Student::STATUS_ENROLLED,
            'changed_by' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        $this->createCardTemplate();

        StudentCard::create([
            'student_id' => $this->student->id,
            'template_id' => 1,
            'card_number' => 'CARD-003',
            'qr_token' => 'qr-003',
            'created_at' => now(),
        ]);

        // Filter by Enrollment
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?category=Enrollment');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertGreaterThan(0, count($data));
        foreach ($data as $entry) {
            $this->assertEquals('Enrollment', $entry['category']);
        }

        // Filter by ID Card
        $responseCard = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?category=ID Card');

        $responseCard->assertStatus(200);
        $cardData = $responseCard->json('data');

        $this->assertGreaterThan(0, count($cardData));
        foreach ($cardData as $entry) {
            $this->assertEquals('ID Card', $entry['category']);
        }
    }

    public function test_filter_by_category_case_insensitive(): void
    {
        EnrollmentStatusHistory::create([
            'student_id' => $this->student->id,
            'old_status' => Student::STATUS_PENDING,
            'new_status' => Student::STATUS_ENROLLED,
            'graduated_status' => Student::STATUS_ENROLLED,
            'changed_by' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        // Title case
        $responseTitle = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?category=Enrollment');

        // Lowercase
        $responseLower = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?category=enrollment');

        // Uppercase
        $responseUpper = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?category=ENROLLMENT');

        $responseTitle->assertStatus(200);
        $responseLower->assertStatus(200);
        $responseUpper->assertStatus(200);

        $titleCount = count($responseTitle->json('data'));
        $lowerCount = count($responseLower->json('data'));
        $upperCount = count($responseUpper->json('data'));

        $this->assertEquals($titleCount, $lowerCount,
            "Title case ($titleCount) vs lowercase ($lowerCount) mismatch");
        $this->assertEquals($titleCount, $upperCount,
            "Title case ($titleCount) vs uppercase ($upperCount) mismatch");
    }

    public function test_filter_by_status_category_student_created(): void
    {
        // Only the "Student Created" event exists by default
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?category=Status');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Status', $data[0]['category']);
        $this->assertEquals('Student Created', $data[0]['title']);
    }

    public function test_invalid_category_returns_400(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?category=InvalidCategory');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 400)
            ->assertJsonPath('error.message', 'Invalid category. Valid categories are: Enrollment, Document, ID Card, Status');
    }

    // ----------------------------------------------------------------
    //  Combined Search + Category
    // ----------------------------------------------------------------

    public function test_combined_search_and_category_filter(): void
    {
        EnrollmentStatusHistory::create([
            'student_id' => $this->student->id,
            'old_status' => Student::STATUS_PENDING,
            'new_status' => Student::STATUS_ENROLLED,
            'graduated_status' => Student::STATUS_ENROLLED,
            'note' => 'Approved for enrollment',
            'changed_by' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        // Filter by Enrollment category AND search for "Approved"
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl . '?category=Enrollment&search=Approved');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should find exactly 1 enrollment event with "Approved" in the description
        $this->assertCount(1, $data);
        foreach ($data as $entry) {
            $this->assertEquals('Enrollment', $entry['category']);
            $this->assertStringContainsStringIgnoringCase('approved', $entry['description']);
        }
    }

    // ----------------------------------------------------------------
    //  Response Structure
    // ----------------------------------------------------------------

    public function test_timeline_response_has_correct_structure(): void
    {
        $this->createCardTemplate();

        StudentCard::create([
            'student_id' => $this->student->id,
            'template_id' => 1,
            'card_number' => 'CARD-004',
            'qr_token' => 'qr-004',
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'category', 'title', 'description', 'created_at'],
                ],
            ]);
    }
}
