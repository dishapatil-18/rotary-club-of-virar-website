<?php
/**
 * Website Settings — Single source of truth for global configuration.
 *
 * Loads settings from the `site_content` table (page = 'settings')
 * and provides sensible defaults. Results are cached per request.
 *
 * Usage:
 *   $ws = getWebsiteSettings($conn);
 *   echo $ws['website_name'];
 *   echo $ws['club_email'];
 */

if (!function_exists('getWebsiteSettings')) {
    function getWebsiteSettings($conn) {
        static $settings = null;
        if ($settings !== null) return $settings;

        $defaults = [
            // ── Branding ──
            'website_logo'       => 'assets/uploads/Logo/rotary-icon.png',
            'website_favicon'    => 'assets/uploads/Logo/rotary-icon.png',
            'website_name'       => 'Rotary Club of Virar',
            'website_short_name' => 'RC Virar',
            'browser_title'      => 'Rotary Club of Virar - Service Above Self',

            // ── General Settings ──
            'timezone'           => 'Asia/Kolkata',
            'date_format'        => 'dd/mm/yyyy',
            'copyright_year'     => (string) date('Y'),

            // ── Rotary Club Profile ──
            'club_name'          => 'Rotary Club of Virar',
            'club_district'      => 'District 3131',
            'club_number'        => '',
            'club_charter_date'  => '',
            'club_meeting_day'   => '',
            'club_meeting_time'  => '',
            'club_meeting_venue' => '',
            'club_address'       => 'Shop No 12, Bhakti Building, Bhau Nagar Road, Tirupati Nagar Phase 2, Virar West, 401303, India',
            'club_email'         => 'rotaryclubofvirar@gmail.com',
            'club_phone'         => '+91 77969 31555',
            'club_website'       => '',

            // ── System Defaults ──
            'default_upload_size'  => '10',
            'image_quality'        => '85',
            'default_pagination'   => '10',
            'items_per_page'       => '12',
        ];

        $loaded = [];
        if (isset($conn)) {
            $r = $conn->query("SELECT section, content FROM site_content WHERE page = 'settings'");
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    if ($row['content'] !== null && $row['content'] !== '') {
                        $loaded[$row['section']] = $row['content'];
                    }
                }
            }
        }

        $settings = array_merge($defaults, $loaded);
        return $settings;
    }
}

if (!function_exists('getWebsiteSetting')) {
    function getWebsiteSetting($conn, $key, $fallback = '') {
        $ws = getWebsiteSettings($conn);
        return $ws[$key] ?? $fallback;
    }
}
