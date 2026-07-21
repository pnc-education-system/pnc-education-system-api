<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Card - {{ $student->student_id_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 40px;
        }
        .card-container {
            width: 320px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            text-align: center;
        }
        .card-header {
            background-color: #1e3a8a;
            color: #ffffff;
            padding: 16px;
        }
        .card-header h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .card-header p {
            margin: 4px 0 0 0;
            font-size: 11px;
            opacity: 0.8;
        }
        .card-body {
            padding: 20px;
        }
        .photo-box {
            width: 110px;
            height: 130px;
            margin: 0 auto 15px auto;
            border-radius: 8px;
            border: 2px solid #cbd5e1;
            overflow: hidden;
        }
        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .student-name {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .student-id {
            font-size: 14px;
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 12px;
        }
        .info-row {
            font-size: 12px;
            color: #475569;
            margin-bottom: 15px;
        }
        .qr-box {
            margin-top: 10px;
        }
        .qr-box img {
            width: 100px;
            height: 100px;
        }
        .card-footer {
            background-color: #f8fafc;
            border-top: 1px solid #f1f5f9;
            padding: 10px;
            font-size: 10px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <div class="card-container">
        <div class="card-header">
            <h2>PNC Education</h2>
            <p>Student Identification Card</p>
        </div>

        <div class="card-body">
            <div class="photo-box">
                <img src="{{ $photoBase64 }}" alt="Student Photo">
            </div>

            <div class="student-name">{{ $student->full_name }}</div>
            <div class="student-id">ID: {{ $student->student_id_no }}</div>

            <div class="info-row">
                <strong>Intake Year:</strong> {{ $student->intake_year ?? 'N/A' }}
            </div>

            <div class="qr-box">
                <img src="{{ $qrCodeBase64 }}" alt="QR Code">
            </div>
        </div>

        <div class="card-footer">
            Official Academic Identity Document
        </div>
    </div>

</body>
</html>
