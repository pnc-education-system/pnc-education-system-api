<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

// Setup
$role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
$perm = Permission::create(['name' => 'Import', 'slug' => 'students.import', 'module' => 'students']);
$role->permissions()->sync([$perm->id]);
$user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true]);
SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);

$importService = app()->make(\App\Services\Student\StudentImportService::class);
try {
    $result = $importService->commit(
        [['student_id_no' => 'ST001', 'full_name' => 'Test', 'gender' => 'Male', 'dob' => '2000-01-01', 'selection_batch_id' => 1, 'enrollment_status' => 'Pending', 'intake_year' => 2025]],
        'test.xlsx',
        $user->id
    );
    echo "Success: " . json_encode($result) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
