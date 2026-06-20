<?php

namespace App\Support;

class AppearanceDefaults
{
    public static function colors(): array
    {
        return [
            'primary' => '#3b5bdb',
            'secondary' => '#6366f1',
            'accent' => '#06b6d4',
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'error' => '#dc2626',
        ];
    }

    public static function announcements(): array
    {
        return [
            [
                'id' => 'ann-1',
                'text' => 'Special Offer: Get 20% off on Annual Membership — Limited time only!',
                'ctaLabel' => 'Grab Deal',
                'ctaHref' => '/plans',
                'priority' => 1,
                'active' => true,
                'gradient' => 'from-indigo-600 via-purple-600 to-blue-600',
            ],
            [
                'id' => 'ann-2',
                'text' => 'New Branch Opening — Register your interest now',
                'ctaLabel' => 'Know More',
                'ctaHref' => '/branches',
                'priority' => 2,
                'active' => true,
                'gradient' => 'from-emerald-600 via-teal-600 to-cyan-600',
            ],
        ];
    }

    public static function meta(): array
    {
        return [
            'default_title' => 'StudyPoint — Premium Study Library',
            'default_description' => 'Join StudyPoint for 24/7 AC study halls, biometric access, high-speed Wi-Fi, and flexible membership plans.',
            'keywords' => 'study library, reading room, study hall, library membership, Mumbai study space',
            'author' => 'StudyPoint',
            'og_image' => null,
            'twitter_card' => 'summary_large_image',
            'robots' => 'index,follow',
            'pages' => [
                '/' => [
                    'title' => 'Premium Study Library & Reading Room',
                    'description' => 'Flexible daily, monthly and annual passes. AC halls, Wi-Fi, biometric entry.',
                ],
                '/about' => [
                    'title' => 'About StudyPoint',
                    'description' => 'Learn about our mission to provide focused study environments.',
                ],
                '/facilities' => [
                    'title' => 'Facilities & Amenities',
                    'description' => 'AC study halls, Wi-Fi, CCTV security, power backup and lockers.',
                ],
                '/plans' => [
                    'title' => 'Membership Plans & Pricing',
                    'description' => 'Compare daily, weekly, monthly and annual membership plans.',
                ],
                '/branches' => [
                    'title' => 'Our Branches',
                    'description' => 'Find StudyPoint branches across Mumbai, Thane, Pune and Nashik.',
                ],
                '/contact' => [
                    'title' => 'Contact Us',
                    'description' => 'Get in touch for admissions, enquiries and support.',
                ],
                '/admission' => [
                    'title' => 'Online Admission',
                    'description' => 'Apply for StudyPoint membership online.',
                ],
                '/login' => [
                    'title' => 'Login',
                    'description' => 'Sign in to student portal, branch manager or super admin.',
                ],
            ],
        ];
    }

    public static function all(): array
    {
        return [
            'mode' => 'light',
            'colors' => self::colors(),
            'fontFamily' => 'Inter',
            'borderRadius' => 'lg',
            'announcements' => self::announcements(),
            'logo_url' => null,
            'favicon_url' => null,
            'id_card_accent_image_url' => null,
            'id_card_back_image_url' => null,
            'site_name' => 'StudyPoint',
            'site_tagline' => 'Study Library',
            'announcement_bar_visible' => true,
            'meta' => self::meta(),
        ];
    }

    public static function merge(array $stored): array
    {
        $defaults = self::all();

        $storedMeta = is_array($stored['meta'] ?? null) ? $stored['meta'] : [];
        $defaultPages = $defaults['meta']['pages'];
        $storedPages = is_array($storedMeta['pages'] ?? null) ? $storedMeta['pages'] : [];

        return [
            'mode' => $stored['mode'] ?? $defaults['mode'],
            'colors' => array_merge($defaults['colors'], is_array($stored['colors'] ?? null) ? $stored['colors'] : []),
            'fontFamily' => $stored['fontFamily'] ?? $defaults['fontFamily'],
            'borderRadius' => $stored['borderRadius'] ?? $defaults['borderRadius'],
            'announcements' => is_array($stored['announcements'] ?? null) ? $stored['announcements'] : $defaults['announcements'],
            'logo_url' => $stored['logo_url'] ?? $defaults['logo_url'],
            'favicon_url' => $stored['favicon_url'] ?? $defaults['favicon_url'],
            'id_card_accent_image_url' => $stored['id_card_accent_image_url'] ?? $defaults['id_card_accent_image_url'],
            'id_card_back_image_url' => $stored['id_card_back_image_url'] ?? $defaults['id_card_back_image_url'],
            'site_name' => $stored['site_name'] ?? $defaults['site_name'],
            'site_tagline' => $stored['site_tagline'] ?? $defaults['site_tagline'],
            'announcement_bar_visible' => array_key_exists('announcement_bar_visible', $stored)
                ? filter_var($stored['announcement_bar_visible'], FILTER_VALIDATE_BOOLEAN)
                : $defaults['announcement_bar_visible'],
            'meta' => [
                'default_title' => $storedMeta['default_title'] ?? $defaults['meta']['default_title'],
                'default_description' => $storedMeta['default_description'] ?? $defaults['meta']['default_description'],
                'keywords' => $storedMeta['keywords'] ?? $defaults['meta']['keywords'],
                'author' => $storedMeta['author'] ?? $defaults['meta']['author'],
                'og_image' => $storedMeta['og_image'] ?? $defaults['meta']['og_image'],
                'twitter_card' => $storedMeta['twitter_card'] ?? $defaults['meta']['twitter_card'],
                'robots' => $storedMeta['robots'] ?? $defaults['meta']['robots'],
                'pages' => array_merge($defaultPages, $storedPages),
            ],
        ];
    }
}
