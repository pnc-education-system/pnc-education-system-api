<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$headers = ['student_id_no','full_name','gender','dob','phone','email','province','high_school','selection_batch_id','enrollment_status','intake_year'];
$col='A'; foreach($headers as $h) $sheet->setCellValue($col++.'1',$h);

$data = [
    ['ST0001','John Doe','Male','2000-05-15','012345678','john@example.com','Phnom Penh','Chaktomuk HS',1,'Pending',2025],
    ['ST0002','Jane Smith','Female','2001-08-22','098765432','jane@example.com','Siem Reap','Angkor HS',2,'Pending',2025],
    ['ST0003','Sok Dara','Male','1999-12-01','011223344','sok@example.com','Battambang','Battambang HS',1,'Enrolled',2024],
    ['ST0004','Chan Sophea','Female','2002-03-14','010203040','sophea@example.com','Kampong Cham','KC HS',1,'Pending',2025],
    ['ST0005','Meas Bora','Male','2000-07-19','015566777','bora@example.com','Takeo','Takeo HS',2,'Pending',2025],
    ['ST0006','Srey Rath','Female','2001-11-30','016677888','srey@example.com','Kandal','Kandal HS',1,'Pending',2025],
    ['ST0007','Vann Sak','Male','1998-06-10','017788999','vann@example.com','Pursat','Pursat HS',2,'Pending',2025],
    ['ST0008','Sopheak Kim','Female','2002-09-05','019988776','sopheak@example.com','Kratie','Kratie HS',1,'Pending',2025],
    ['ST0009','Rithy Bun','Male','1999-04-18','013344556','rithy@example.com','Ratanakiri','RK HS',1,'Pending',2025],
    ['ST0010','Chea Lim','Female','2000-12-25','012233445','chea@example.com','Kampot','Kampot HS',2,'Pending',2025],
];

$row=2;
foreach($data as $r) {
    $col='A';
    foreach($r as $v) $sheet->setCellValue($col++.$row,$v);
    $row++;
}
$writer = new Xlsx($ss);
$writer->save('test-10-rows.xlsx');
echo "Created test-10-rows.xlsx with 10 student rows\n";
