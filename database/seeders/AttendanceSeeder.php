<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', UserRole::Student)->get();
        $scanners = User::where('role', '!=', UserRole::Student)->get();

        if ($students->isEmpty() || $scanners->isEmpty()) {
            $this->command->warn('No students or scanners found — skipping AttendanceSeeder.');
            return;
        }

        // Spread records across the last 4 months so the "month" filter has real data
        $months = collect(range(0, 3))->map(fn($i) => now()->subMonths($i));

        foreach ($months as $month) {
            // random number of attendance records per month per student
            foreach ($students as $student) {
                $recordsThisMonth = rand(1, 4);

                for ($i = 0; $i < $recordsThisMonth; $i++) {
                    Attendance::create([
                        'student_id' => $student->id,
                        'scanned_by' => $scanners->random()->id,
                        'created_at' => $month->copy()
                            ->startOfMonth()
                            ->addDays(rand(0, $month->daysInMonth - 1))
                            ->addHours(rand(7, 20))
                            ->addMinutes(rand(0, 59)),
                    ]);
                }
            }
        }
    }
}