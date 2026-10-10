<?php
declare(strict_types=1);

// Copy to config/mail.php on the server, restrict permissions to 0600, and
// provision a Hostinger token restricted to the chosen mailbox. Never commit it.
// These values override only the mail section of config/app.php.
return [
    'transport' => 'hostinger',
    'from_name' => 'Union of Kingdoms',
    'hostinger' => [
        'mailbox_resource_id' => getenv('CONQUER_MAILBOX_RESOURCE_ID') ?: '',
        'api_token' => getenv('CONQUER_MAIL_API_TOKEN') ?: '',
    ],
];
