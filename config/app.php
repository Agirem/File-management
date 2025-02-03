<?php

return [
    'folders' => [
        'private' => 'private_files',    // Dossier privé pour l'administrateur
        'shared' => 'shared_files'       // Dossier partagé visible par tous
    ],
    'auth' => [
        'admin' => [
            'username' => 'admin',
            'password' => 'password',     // À changer !
            'role' => 'admin'
        ],
        'guest' => [
            'username' => 'guest',
            'password' => 'guest123',     // À changer !
            'role' => 'guest'
        ]
    ],
    'allowed_extensions' => ['mp3', 'mp4', 'pdf', 'jpg', 'jpeg', 'png', 'txt'],
    'mime_types' => [
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'txt' => 'text/plain'
    ]
]; 