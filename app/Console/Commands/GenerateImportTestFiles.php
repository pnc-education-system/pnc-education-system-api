<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateImportTestFiles extends Command
{
    protected $signature = 'import:generate-test-files';

    protected $description = 'Generate test Excel files for import validation testing in Postman';

    public function handle(): void
    {
        $dir = storage_path('app/testing/imports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->createValidUpload($dir);
        $this->createExtraColumnsFile($dir);
        $this->createDuplicateFile($dir);

        $this->info('Test files generated at: ' . $dir);
        $this->warn('Use these files in Postman as the "file" form-data field.');
    }

    private function createValidUpload(string $dir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'student_id_no', 'full_name', 'gender', 'dob', 'phone',
            'email', 'province', 'high_school', 'selection_batch_id',
            'enrollment_status', 'intake_year',
        ];
        $rows = [
            ['ST0001', 'John Doe',        'Male',   '2000-01-15', '0123456789', 'john@test.com',   'Phnom Penh',  'HS A', 1, 'Pending',  2024],
            ['ST0002', 'Jane Smith',      'Female', '2001-05-20', '0987654321', 'jane@test.com',   'Battambang',  'HS B', 1, 'Enrolled', 2024],
            ['ST0003', 'Sokha Chea',      'Male',   '1999-11-02', null,          null,              'Kandal',      'HS C', 1, null,       2024],
            ['ST0004', 'Srey Mom',        'Female', '2002-03-18', '0112233445', 'srey@test.com',   'Siem Reap',   'HS D', 2, 'Pending',  2024],
            ['ST0005', 'Vannak Phorn',    'Male',   '2000-07-30', null,          'vannak@test.com', 'Takeo',       'HS E', 2, 'Enrolled', 2024],
        ];

        $sheet->fromArray(array_merge([$headers], $rows), null, 'A1', true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($dir . '/valid_upload.xlsx');

        $this->info('  [OK] valid_upload.xlsx — 5 rows, 11 columns (all system columns)');
        $spreadsheet->disconnectWorksheets();
    }

    private function createExtraColumnsFile(string $dir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'student_id_no', 'full_name', 'gender', 'dob', 'phone',
            'email', 'province', 'high_school', 'selection_batch_id',
            'enrollment_status', 'intake_year', 'random_column', 'another_extra',
        ];
        $rows = [
            ['ST0001', 'Test User', 'Male', '2000-01-01', '0123456789', 'test@test.com', 'PP', 'HS X', 1, 'Pending', 2024, 'unexpected', 'extra'],
        ];

        $sheet->fromArray(array_merge([$headers], $rows), null, 'A1', true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($dir . '/extra_columns.xlsx');

        $this->info('  [OK] extra_columns.xlsx — has random_column and another_extra (should be rejected)');
        $spreadsheet->disconnectWorksheets();
    }

    private function createDuplicateFile(string $dir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'student_id_no', 'full_name', 'gender', 'dob', 'phone',
            'email', 'province', 'high_school', 'selection_batch_id',
            'enrollment_status', 'intake_year',
        ];
        $rows = [
            ['ST0001', 'John Doe',        'Male',   '2000-01-15', '0123456789', 'john@test.com', 'Phnom Penh', 'HS A', 1, 'Pending',  2024],
            ['ST0002', 'Jane Smith',      'Female', '2001-05-20', '0987654321', 'jane@test.com', 'Battambang', 'HS B', 1, 'Enrolled', 2024],
            ['ST0001', 'Johnny Doe',      'Male',   '2000-01-15', null,          null,             null,         null,   1, 'Pending',  2024],
            ['ST0003', 'Sokha Chea',      'Male',   '1999-11-02', '0112233445', null,             'Kandal',     'HS C', 2, 'Pending',  2024],
        ];

        $sheet->fromArray(array_merge([$headers], $rows), null, 'A1', true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($dir . '/duplicate_students.xlsx');

        $this->info('  [OK] duplicate_students.xlsx — ST0001 appears twice (row 1 + row 3), should keep the most complete row');
        $spreadsheet->disconnectWorksheets();
    }
}
