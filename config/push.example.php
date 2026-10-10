<?php
declare(strict_types=1);
// Copy to private, Git-ignored config/push.php, or set UOK_PUSH_* environment values.
// Generate an application-specific key pair with bin/create-push-keys.php.
return [
    'enabled' => false,
    'subject' => 'mailto:operator@example.com',
    'public_key' => '',
    'private_key' => '',
    'native_enabled' => false,
    'firebase_project_id' => '',
    // Absolute path OUTSIDE this project/web root. Keep service-account JSON private.
    'firebase_credentials_file' => '',
];
