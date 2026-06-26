<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Services\AttendanceService;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'code' => 'HO',
                'name' => 'StudyPoint Head Office',
                'legal_name' => 'StudyPoint Learning Spaces Pvt. Ltd.',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
                'address' => '123 Knowledge Park, Education City, Mumbai - 400001',
                'manager_phone' => '+91 98765 43210',
                'email' => 'hello@studypoint.in',
                'website' => 'https://studypoint.in',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'currency_symbol' => '₹',
                'opens_at' => '6:00 AM',
                'closes_at' => '11:00 PM',
                'operating_hours' => '6:00 AM – 11:00 PM',
                'gstin' => '27AABCS1429B1ZB',
                'pan' => 'AABCS1429B',
                'capacity' => 150,
                'status' => 'active',
                'is_head_office' => true,
                'is_accepting_admissions' => true,
                'features' => ['AC Study Hall', 'WiFi', 'Biometric Access', 'Lockers'],
            ],
            [
                'code' => 'AND',
                'name' => 'Andheri West',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400053',
                'address' => 'Shop 12, Infinity Mall Road, Andheri West, Mumbai',
                'manager_name' => 'Rahul Desai',
                'manager_phone' => '+91 98765 43211',
                'email' => 'andheri@studypoint.in',
                'opens_at' => '6:00 AM',
                'closes_at' => '11:00 PM',
                'operating_hours' => '6:00 AM – 11:00 PM',
                'capacity' => 120,
                'status' => 'active',
                'is_head_office' => false,
                'is_accepting_admissions' => true,
                'features' => ['AC Study Hall', 'WiFi', 'CCTV', 'Power Backup'],
            ],
            [
                'code' => 'PNE',
                'name' => 'Pune Hinjewadi',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411057',
                'address' => 'Phase 1, Hinjewadi IT Park, Pune',
                'manager_name' => 'Sneha Kulkarni',
                'manager_phone' => '+91 98765 43212',
                'email' => 'pune@studypoint.in',
                'opens_at' => '6:00 AM',
                'closes_at' => '11:00 PM',
                'operating_hours' => '6:00 AM – 11:00 PM',
                'capacity' => 100,
                'status' => 'active',
                'is_head_office' => false,
                'is_accepting_admissions' => true,
                'features' => ['AC Study Hall', 'WiFi', 'Private Cabins', 'Parking'],
            ],
            [
                'code' => 'THN',
                'name' => 'Thane East',
                'city' => 'Thane',
                'state' => 'Maharashtra',
                'pincode' => '400603',
                'address' => '2nd Floor, Eastern Express Highway, Thane East',
                'manager_name' => 'Amit Shah',
                'manager_phone' => '+91 98765 43213',
                'email' => 'thane@studypoint.in',
                'opens_at' => '6:00 AM',
                'closes_at' => '11:00 PM',
                'operating_hours' => '6:00 AM – 11:00 PM',
                'capacity' => 80,
                'status' => 'active',
                'is_head_office' => false,
                'is_accepting_admissions' => true,
                'features' => ['AC Study Hall', 'WiFi', 'Library Access', 'Water Purifier'],
            ],
        ];

        foreach ($branches as $data) {
            $branch = Branch::updateOrCreate(
                ['code' => $data['code']],
                $data,
            );

            if (! $branch->attendance_qr_token) {
                $branch->update([
                    'attendance_qr_token' => AttendanceService::generateBranchAttendanceToken(),
                ]);
            }
        }
    }
}
