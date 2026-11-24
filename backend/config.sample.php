<?php
// Legendary Vero website chat: copy this file to config.php IN THIS SAME FOLDER on Hostinger
// (hPanel > File Manager > legendaryvero.com > public_html > backend) and paste the real key.
// config.php is never committed to GitHub (.gitignore); a deploy never overwrites it.
return [
    'anthropic_api_key' => 'PASTE_ANTHROPIC_KEY_HERE',
    'anthropic_model'   => 'claude-sonnet-5',
    'business_phone'    => '(772) 257-6109',
    'timezone'          => 'America/New_York',
    'allowed_origin'    => ['https://legendaryvero.com', 'https://www.legendaryvero.com'],
    'office_email'      => 'win@legendaryvero.com',
    // Filled in once Legendary's GHL sub-account exists (leads + quotes go there):
    'ghl_private_token' => '',
    'ghl_location_id'   => '',
    'ghl_calendar_id'   => '',
];
