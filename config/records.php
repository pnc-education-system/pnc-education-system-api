<?php

return [
    'attachment' => [
        'max_size_kb' => env('RECORD_ATTACHMENT_MAX_SIZE_KB', 10240),
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'doc', 'docx'],
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'storage_path' => 'records/attachments',
    ],
];
