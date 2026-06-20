<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Expense;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::query()->where('status', 'active')->orderBy('id')->get();

        if ($branches->isEmpty()) {
            $this->command?->warn('No branches found — skipping expense seed.');

            return;
        }

        $pickBranch = fn (int $index) => $branches[$index % $branches->count()];

        $rows = [
            ['title' => 'Electricity Bill — June', 'amount' => 8500, 'category' => 'utilities', 'status' => 'approved', 'days_ago' => 3],
            ['title' => 'Water & RO Maintenance', 'amount' => 2200, 'category' => 'utilities', 'status' => 'approved', 'days_ago' => 8],
            ['title' => 'Staff Salary — May', 'amount' => 45000, 'category' => 'salary', 'status' => 'approved', 'days_ago' => 12],
            ['title' => 'Part-time Helper Salary', 'amount' => 12000, 'category' => 'salary', 'status' => 'pending', 'days_ago' => 2],
            ['title' => 'AC Service & Gas Refill', 'amount' => 6500, 'category' => 'maintenance', 'status' => 'approved', 'days_ago' => 15],
            ['title' => 'Biometric Device Repair', 'amount' => 3500, 'category' => 'maintenance', 'status' => 'pending', 'days_ago' => 1],
            ['title' => 'Cleaning Supplies', 'amount' => 1800, 'category' => 'supplies', 'status' => 'approved', 'days_ago' => 6],
            ['title' => 'Stationery & Printouts', 'amount' => 950, 'category' => 'supplies', 'status' => 'approved', 'days_ago' => 4],
            ['title' => 'WiFi Router Replacement', 'amount' => 4200, 'category' => 'maintenance', 'status' => 'rejected', 'days_ago' => 20],
            ['title' => 'Generator Diesel', 'amount' => 5600, 'category' => 'utilities', 'status' => 'approved', 'days_ago' => 10],
            ['title' => 'Security Guard Salary', 'amount' => 15000, 'category' => 'salary', 'status' => 'approved', 'days_ago' => 14],
            ['title' => 'Tea & Pantry Supplies', 'amount' => 2400, 'category' => 'supplies', 'status' => 'pending', 'days_ago' => 0],
        ];

        foreach ($rows as $i => $row) {
            $branch = $pickBranch($i);
            $date = Carbon::today()->subDays($row['days_ago']);

            Expense::updateOrCreate(
                [
                    'title' => $row['title'],
                    'branch_id' => $branch->id,
                    'expense_date' => $date->toDateString(),
                ],
                [
                    'amount' => $row['amount'],
                    'category' => $row['category'],
                    'status' => $row['status'],
                ],
            );
        }

        $this->command?->info('Seeded '.count($rows).' sample expenses.');
    }
}
