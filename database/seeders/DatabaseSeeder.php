<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Facility;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /** Legacy demo accounts removed on every seed run */
    private const DEMO_EMAILS = [
        'manager@studypoint.in',
        'student@studypoint.in',
    ];

    public function run(): void
    {
        foreach (['super_admin', 'branch_manager', 'staff', 'receptionist', 'attendance_operator', 'student'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->call(PermissionSeeder::class);

        $branches = [
            [
                'code' => 'HO',
                'name' => 'StudyPoint Head Office',
                'city' => 'Mumbai',
                'address' => '123 Knowledge Park, Education City, Mumbai - 400001',
                'manager_phone' => '+91 98765 43210',
                'email' => 'hello@studypoint.in',
                'opens_at' => '6:00 AM',
                'closes_at' => '11:00 PM',
                'operating_hours' => '6:00 AM – 11:00 PM',
                'capacity' => 120,
                'features' => ['AC Hall', 'WiFi', 'Individual Cabins', 'Library', 'Parking', 'CCTV'],
                'is_accepting_admissions' => true,
                'is_head_office' => true,
                'social_facebook' => '',
                'social_instagram' => '',
                'social_twitter' => '',
                'social_youtube' => '',
            ],
            [
                'code' => 'AND-W',
                'name' => 'Andheri West',
                'city' => 'Mumbai',
                'address' => 'Shop 12, Versova Link Road, Near Metro Station, Andheri West, Mumbai - 400053',
                'manager_name' => 'Rohit Desai',
                'manager_phone' => '+91 98765 43210',
                'operating_hours' => '6:00 AM – 11:00 PM',
                'capacity' => 120,
                'features' => ['AC Hall', 'WiFi', 'Individual Cabins', 'Library', 'Parking', 'CCTV'],
                'is_accepting_admissions' => true,
            ],
            [
                'code' => 'THN-E',
                'name' => 'Thane East',
                'city' => 'Thane',
                'address' => '2nd Floor, Viviana Mall Annex, Pokhran Road, Thane East - 400601',
                'manager_name' => 'Priya Nair',
                'manager_phone' => '+91 98765 43211',
                'operating_hours' => '6:00 AM – 11:00 PM',
                'capacity' => 90,
                'features' => ['AC Hall', 'WiFi', 'Individual Cabins', 'Library', 'CCTV'],
                'is_accepting_admissions' => true,
            ],
            [
                'code' => 'PUN-H',
                'name' => 'Pune Hinjewadi',
                'city' => 'Pune',
                'address' => 'Phase 1, IT Park Road, Near Wakad Bridge, Hinjewadi, Pune - 411057',
                'manager_name' => 'Amit Kumar',
                'manager_phone' => '+91 98765 43212',
                'operating_hours' => '6:00 AM – 12:00 AM',
                'capacity' => 150,
                'features' => ['AC Hall', 'WiFi', 'Individual Cabins', 'Library', 'Parking', 'CCTV', 'Cafeteria'],
                'is_accepting_admissions' => true,
            ],
            [
                'code' => 'NSK-R',
                'name' => 'Nashik Road',
                'city' => 'Nashik',
                'address' => 'Opp. Nashik Road Railway Station, College Road, Nashik - 422101',
                'manager_name' => 'Sunita Patil',
                'manager_phone' => '+91 98765 43213',
                'operating_hours' => '6:00 AM – 10:00 PM',
                'capacity' => 80,
                'features' => ['AC Hall', 'WiFi', 'Library', 'CCTV'],
                'is_accepting_admissions' => false,
            ],
        ];

        foreach ($branches as $b) {
            $branch = Branch::withTrashed()->firstOrNew(['code' => $b['code']]);
            $branch->fill($b + ['status' => 'active']);
            if ($branch->trashed()) {
                $branch->restore();
            }
            $branch->save();
        }

        $this->seedFacilities();
        $this->call(FaqSeeder::class);
        $this->call(TestimonialSeeder::class);
        $this->call(ExpenseSeeder::class);

        $plans = [
            [
                'slug' => 'daily',
                'name' => 'Daily Pass',
                'category' => 'Daily',
                'duration_days' => 1,
                'duration_months' => 1,
                'price' => 99,
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access'],
            ],
            [
                'slug' => 'weekly',
                'name' => 'Weekly Pass',
                'category' => 'Weekly',
                'duration_days' => 7,
                'duration_months' => 1,
                'price' => 499,
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'WhatsApp Alerts'],
            ],
            [
                'slug' => 'fortnightly',
                'name' => 'Fortnightly Pass',
                'category' => 'Fortnightly',
                'duration_days' => 15,
                'duration_months' => 1,
                'price' => 799,
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access'],
            ],
            [
                'slug' => 'monthly',
                'name' => 'Monthly Pass',
                'category' => 'Monthly',
                'duration_days' => 30,
                'duration_months' => 1,
                'price' => 1499,
                'badge' => 'Popular',
                'is_featured' => true,
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'Individual Cabin', 'WhatsApp Alerts'],
            ],
            [
                'slug' => 'quarterly',
                'name' => 'Quarterly Pass',
                'category' => 'Quarterly',
                'duration_days' => 90,
                'duration_months' => 3,
                'price' => 3999,
                'badge' => 'Save 11%',
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'Individual Cabin', 'Multi-Branch Access', 'WhatsApp Alerts'],
            ],
            [
                'slug' => 'half-yearly',
                'name' => 'Half-Yearly Pass',
                'category' => 'Half-Yearly',
                'duration_days' => 180,
                'duration_months' => 6,
                'price' => 7499,
                'badge' => 'Save 17%',
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'Individual Cabin', 'Multi-Branch Access', 'Priority Access', 'WhatsApp Alerts'],
            ],
            [
                'slug' => 'yearly',
                'name' => 'Yearly Pass',
                'category' => 'Yearly',
                'duration_days' => 365,
                'duration_months' => 12,
                'price' => 12999,
                'badge' => 'Best Value',
                'highlights' => ['AC Study Hall', 'High-Speed WiFi', 'Biometric Access', 'Library Access', 'Individual Cabin', 'Multi-Branch Access', 'Priority Access', '24×7 Access', 'WhatsApp Alerts'],
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(['slug' => $p['slug']], $p + ['status' => 'active']);
        }

        $this->removeDemoUsers();

        $this->seedSuperAdmin();
    }

    private function removeDemoUsers(): void
    {
        Student::withTrashed()->where('student_code', 'SP2024001')->forceDelete();

        $demoUsers = User::withTrashed()->whereIn('email', self::DEMO_EMAILS)->get();
        foreach ($demoUsers as $user) {
            Student::withTrashed()->where('user_id', $user->id)->forceDelete();
            $user->roles()->detach();
            $user->forceDelete();
        }
    }

    private function seedFacilities(): void
    {
        $facilities = [
            [
                'slug' => 'ac',
                'title' => 'AC Study Hall',
                'short_description' => 'Temperature-controlled halls',
                'description' => 'Fully air-conditioned study halls maintained at optimal temperature (22–24°C) for maximum comfort and concentration throughout the day.',
                'bullet_points' => ['Individual temperature control zones', 'Regular HVAC maintenance', 'Humidity controlled environment'],
                'icon' => 'wind',
                'sort_order' => 1,
                'show_in_nav' => true,
                'show_on_home' => true,
                'show_on_page' => true,
            ],
            [
                'slug' => 'wifi',
                'title' => 'High-Speed WiFi',
                'short_description' => '100 Mbps dedicated fiber',
                'description' => 'Dedicated 100 Mbps fiber-optic internet connection with seamless roaming across all areas of the facility.',
                'bullet_points' => ['100 Mbps dedicated line', 'Separate student & admin networks', 'No throttling or data limits'],
                'icon' => 'wifi',
                'sort_order' => 2,
                'show_in_nav' => true,
                'show_on_home' => true,
                'show_on_page' => true,
            ],
            [
                'slug' => 'cctv',
                'title' => 'CCTV Security',
                'short_description' => '24×7 HD surveillance',
                'description' => '24×7 HD CCTV monitoring across all study areas, corridors, entrances, and parking zones with 30-day recording.',
                'bullet_points' => ['HD cameras at all points', '30-day footage retention', 'Remote monitoring by security team'],
                'icon' => 'shield',
                'sort_order' => 3,
                'show_in_nav' => true,
                'show_on_home' => true,
                'show_on_page' => true,
            ],
            [
                'slug' => 'cabins',
                'title' => 'Individual Cabins',
                'short_description' => 'Private study pods',
                'description' => 'Private study cabins with ergonomic chairs, personal charging ports, and noise-cancellation panels for distraction-free focus.',
                'bullet_points' => ['Sound-dampened partitions', 'Ergonomic seating', 'Personal power socket & USB'],
                'icon' => 'camera',
                'sort_order' => 4,
                'show_in_nav' => true,
                'show_on_home' => true,
                'show_on_page' => true,
            ],
            [
                'slug' => 'library',
                'title' => 'Library Access',
                'short_description' => '5,000+ curated books',
                'description' => 'Curated physical library with 5,000+ books covering competitive exams (UPSC, SSC, Banking, NEET, JEE, CA) and general knowledge.',
                'bullet_points' => ['5,000+ curated books', 'New arrivals monthly', 'Borrow up to 2 books at a time'],
                'icon' => 'book-marked',
                'sort_order' => 5,
                'show_in_nav' => true,
                'show_on_home' => true,
                'show_on_page' => true,
            ],
            [
                'slug' => 'power',
                'title' => 'Power Backup',
                'short_description' => '100% UPS + generator',
                'description' => '100% power backup with high-capacity UPS and diesel generator ensuring zero downtime, even during extended outages.',
                'bullet_points' => ['Zero power interruption', 'Generator + UPS combo', 'All outlets backed up'],
                'icon' => 'zap',
                'sort_order' => 6,
                'show_in_nav' => true,
                'show_on_home' => true,
                'show_on_page' => true,
            ],
            [
                'slug' => 'parking',
                'title' => 'Parking',
                'short_description' => '2-wheeler & 4-wheeler',
                'description' => 'Spacious parking facility with dedicated 2-wheeler and 4-wheeler zones, covered parking available for annual members.',
                'bullet_points' => ['100+ 2-wheeler slots', '25 4-wheeler slots', 'Covered parking for annual members'],
                'icon' => 'car',
                'sort_order' => 7,
                'show_in_nav' => true,
                'show_on_home' => true,
                'show_on_page' => true,
            ],
            [
                'slug' => 'water',
                'title' => 'Water Facility',
                'short_description' => 'RO purified water on every floor',
                'description' => 'RO purified drinking water dispensers on every floor, along with modern washroom facilities maintained with regular housekeeping.',
                'bullet_points' => ['RO purified water', 'Hourly housekeeping', 'Hot & cold water dispensers'],
                'icon' => 'droplets',
                'sort_order' => 8,
                'show_in_nav' => false,
                'show_on_home' => false,
                'show_on_page' => true,
            ],
            [
                'slug' => 'access-24x7',
                'title' => '24×7 Access',
                'short_description' => 'Round-the-clock access for annual members',
                'description' => 'Round-the-clock biometric access available for annual members across select branches.',
                'bullet_points' => ['Biometric entry', 'Annual member benefit', 'Select branches'],
                'icon' => 'users',
                'sort_order' => 9,
                'show_in_nav' => false,
                'show_on_home' => true,
                'show_on_page' => false,
            ],
        ];

        foreach ($facilities as $facility) {
            Facility::updateOrCreate(['slug' => $facility['slug']], $facility + ['status' => 'active']);
        }
    }

    private function seedSuperAdmin(): void
    {
        $email = env('SEED_SUPER_ADMIN_EMAIL', 'admin@studypoint.in');
        $name = env('SEED_SUPER_ADMIN_NAME', 'Super Admin');

        $admin = User::withTrashed()->where('email', $email)->first();

        if ($admin) {
            if ($admin->trashed()) {
                $admin->restore();
            }
            // Update only — never reset password on re-seed
            $admin->update([
                'name' => $name,
                'status' => 'active',
            ]);
        } else {
            $admin = User::create([
                'email' => $email,
                'name' => $name,
                'password' => env('SEED_SUPER_ADMIN_PASSWORD', 'demo1234'),
                'status' => 'active',
            ]);
        }

        $admin->syncRoles(['super_admin']);
    }
}
