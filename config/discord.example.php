<?php
declare(strict_types=1);
// Copy to discord.php only on the deployment host. Never commit tokens.
return [
    'enabled' => getenv('UOK_DISCORD_ENABLED') === '1',
    'application_id' => getenv('UOK_DISCORD_APPLICATION_ID') ?: '',
    'public_key' => getenv('UOK_DISCORD_PUBLIC_KEY') ?: '',
    'guild_ids' => array_values(array_filter(array_map('trim', explode(',', getenv('UOK_DISCORD_GUILD_IDS') ?: '')))),
    // Used by the registration CLI only, never sent to the game client.
    'bot_token' => getenv('UOK_DISCORD_BOT_TOKEN') ?: '',
];
