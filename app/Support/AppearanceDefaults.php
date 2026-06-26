<?php

namespace App\Support;

class AppearanceDefaults
{
    public static function policies(): array
    {
        return [
            'privacy_policy' => [
                'effective_date' => '26 June 2026',
                'sections' => [
                    [
                        'title' => 'Information we collect',
                        'body' => [
                            'We collect details you share directly with us, including your name, phone number, email address, branch preference, payment information, and any support requests you submit.',
                            'We also collect basic device, browser, and usage data when you use our website or admin tools so we can keep the service stable and secure.',
                        ],
                    ],
                    [
                        'title' => 'How we use information',
                        'body' => [
                            'We use your data to provide memberships, process payments, send operational updates, support admissions, and improve the platform experience.',
                            'We may also use contact details to send reminders, notifications, and service-related messages through email, SMS, or WhatsApp where permitted.',
                        ],
                    ],
                    [
                        'title' => 'Data sharing and security',
                        'body' => [
                            'We do not sell personal data. We only share information with trusted service providers that help us operate the business, such as payment, communication, and cloud infrastructure vendors.',
                            'We use reasonable technical and organizational safeguards to protect personal information, but no online system can be guaranteed to be completely secure.',
                        ],
                    ],
                    [
                        'title' => 'Your choices',
                        'body' => [
                            'You may contact us to review or update your information, ask about data usage, or request that certain communications be reduced where applicable.',
                            'If you have questions about this policy, please contact our support team using the details on the Contact page.',
                        ],
                    ],
                ],
            ],
            'terms_of_service' => [
                'effective_date' => '26 June 2026',
                'sections' => [
                    [
                        'title' => 'Acceptance of terms',
                        'body' => [
                            'By using StudyPoint services, you agree to follow these terms and any related policies or notices shown on the platform.',
                            'If you do not agree with any part of these terms, please do not use the service.',
                        ],
                    ],
                    [
                        'title' => 'Membership and conduct',
                        'body' => [
                            'Membership plans, access rules, and facility guidelines are subject to availability and branch policies.',
                            'Users must not misuse the platform, interfere with service operations, submit false information, or attempt unauthorized access to admin or student data.',
                        ],
                    ],
                    [
                        'title' => 'Payments and service changes',
                        'body' => [
                            'Fees, taxes, and billing terms may vary by branch, plan, or promotional offer.',
                            'We may update features, operating hours, or service availability when needed to improve operations or comply with legal requirements.',
                        ],
                    ],
                    [
                        'title' => 'Limitation of liability',
                        'body' => [
                            'StudyPoint provides the service on an as-available basis. To the extent permitted by law, we are not responsible for indirect losses caused by usage interruptions, third-party systems, or user-side errors.',
                            'If you need clarification on any specific plan or service rule, contact us before continuing to use the platform.',
                        ],
                    ],
                ],
            ],
            'refund_policy' => [
                'effective_date' => '26 June 2026',
                'sections' => [
                    [
                        'title' => 'Refund eligibility',
                        'body' => [
                            'Refunds, where applicable, are determined by the subscription or payment type, branch policy, and the specific reason for the request.',
                            'Processed services, completed billing periods, and consumed facilities may not be refundable unless required by law or explicitly approved.',
                        ],
                    ],
                    [
                        'title' => 'How to request a refund',
                        'body' => [
                            'Please contact the support team with the payment reference, student or member details, and a short explanation of the issue.',
                            'We may ask for supporting records before reviewing the request and will communicate the outcome after verification.',
                        ],
                    ],
                    [
                        'title' => 'Processing timeline',
                        'body' => [
                            'Approved refunds are typically returned using the original payment method where possible.',
                            'The time it takes for funds to appear in your account can depend on your bank, card issuer, wallet provider, or payment gateway.',
                        ],
                    ],
                    [
                        'title' => 'Adjustments and exceptions',
                        'body' => [
                            'In some cases, we may issue a partial refund, account credit, or adjustment instead of a full reversal.',
                            'Any exceptions to this policy are reviewed case by case and must be confirmed by the StudyPoint team in writing.',
                        ],
                    ],
                ],
            ],
        ];
    }

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
            'footer_copyright_text' => '',
            'footer_developed_by_text' => '',
            'footer_developed_by_url' => '',
            'announcement_bar_visible' => true,
            'meta' => self::meta(),
            'policies' => self::policies(),
        ];
    }

    public static function merge(array $stored): array
    {
        $defaults = self::all();

        $storedMeta = is_array($stored['meta'] ?? null) ? $stored['meta'] : [];
        $storedPolicies = is_array($stored['policies'] ?? null) ? $stored['policies'] : [];
        $defaultPages = $defaults['meta']['pages'];
        $storedPages = is_array($storedMeta['pages'] ?? null) ? $storedMeta['pages'] : [];
        $defaultPolicies = $defaults['policies'];
        $mergedPolicies = [];

        foreach ($defaultPolicies as $policyKey => $defaultPolicy) {
            $storedPolicy = is_array($storedPolicies[$policyKey] ?? null) ? $storedPolicies[$policyKey] : [];
            $storedSections = is_array($storedPolicy['sections'] ?? null) ? $storedPolicy['sections'] : [];
            $defaultSections = is_array($defaultPolicy['sections'] ?? null) ? $defaultPolicy['sections'] : [];
            $mergedSections = [];

            foreach ($defaultSections as $i => $defaultSection) {
                $section = is_array($storedSections[$i] ?? null) ? $storedSections[$i] : [];
                $body = is_array($section['body'] ?? null) ? $section['body'] : [];
                $defaultBody = is_array($defaultSection['body'] ?? null) ? $defaultSection['body'] : [];

                $mergedSections[] = [
                    'title' => (string) ($section['title'] ?? $defaultSection['title'] ?? ''),
                    'body' => $body !== [] ? $body : $defaultBody,
                ];
            }

            $mergedPolicies[$policyKey] = [
                'effective_date' => (string) ($storedPolicy['effective_date'] ?? $defaultPolicy['effective_date'] ?? ''),
                'sections' => $mergedSections,
            ];
        }

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
            'footer_copyright_text' => $stored['footer_copyright_text'] ?? $defaults['footer_copyright_text'],
            'footer_developed_by_text' => $stored['footer_developed_by_text'] ?? $defaults['footer_developed_by_text'],
            'footer_developed_by_url' => $stored['footer_developed_by_url'] ?? $defaults['footer_developed_by_url'],
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
            'policies' => $mergedPolicies,
        ];
    }
}
