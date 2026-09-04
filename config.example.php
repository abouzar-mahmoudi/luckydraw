<?php
/**
 * قرعه‌کشی — optional configuration.
 * Copy to config.php to override defaults. Every key is optional.
 */
return [
    // 'auto' (default) = SQLite if available, otherwise JSON files.
    // Force one with 'sqlite' or 'file'.
    'store' => 'auto',

    // UI language for first-time visitors: 'fa' (default) or 'en'.
    // Every visitor can switch with the FA/EN button (stored in a cookie).
    'default_lang' => 'fa',

    // Fair-use limits for live links (0 = unlimited).
    'max_rooms_total' => 300,   // live rooms on the whole server
    'max_rooms_per_ip' => 30,   // live rooms created from one client address
    'max_signups_total' => 200, // open registration forms (ثبت‌نام) on the whole server
    'max_signups_per_ip' => 20, // registration forms created from one client address

    // Who may embed the pages in an <iframe>. Empty (default) = anyone on the
    // network (handy for kiosk/dashboard pages); "'self'" = only this site; "'none'" = nobody.
    'frame_ancestors' => '',

    // Extra origins allowed to call the API from a browser (CSRF guard).
    // Only needed behind a reverse proxy that rewrites the Host header AND
    // serves very old browsers without Sec-Fetch-Site support.
    'allowed_origins' => [
        // 'https://draw.example.local',
    ],
];
