<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Financial;
use App\Models\User;
use Illuminate\Database\Seeder;

class FinancialSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', UserRole::Student)->get();
        $assistants = User::where('role', UserRole::Assistant)->get();

        if ($students->isEmpty() || $assistants->isEmpty()) {
            $this->command->warn('No students or assistants found — skipping FinancialSeeder.');

            return;
        }

        $titles = [
            'دفع المصروفات الدراسية',
            'قسط شهري مستحق',
            'رسوم الأنشطة المدرسية',
            'خصم للتفوق الدراسي',
            'غرامة تأخير الدفع',
            'رسوم الكتب الدراسية',
            'دفعة مقدمة للفصل الدراسي',
            'استرداد مبلغ مدفوع',
            'رسوم النقل المدرسي',
            'قسط الدورة الصيفية',
        ];

        $descriptions = [
            'تم استلام المبلغ نقداً من ولي الأمر مع إصدار إيصال رسمي.',
            'سداد القسط الشهري عن الشهر الحالي مع تحديث حالة الحساب.',
            'رسوم الاشتراك في الأنشطة المدرسية والرحلات التعليمية.',
            'تم تطبيق خصم تشجيعي لتفوق الطالب في الفصل الدراسي.',
            'غرامة تأخير بسيطة بسبب تجاوز موعد السداد المحدد.',
            'رسوم الكتب والمذكرات الدراسية للفصل الحالي.',
            'دفعة مقدمة لحجز مقعد الطالب في الفصل الدراسي الجديد.',
            'تم رد جزء من المبلغ المدفوع بالزيادة إلى ولي الأمر.',
            'رسوم اشتراك خدمة النقل المدرسي للشهر الحالي.',
            'قسط الدورة الصيفية المكثفة مع تأكيد التسجيل.',
        ];

        // Uneven per-student distribution so group filter counts differ
        // and sorting (most financials first) is visible.
        $shuffled = $students->shuffle();
        $plan = [];

        if ($shuffled->count() >= 3) {
            $plan[$shuffled[0]->id] = 6;
            $plan[$shuffled[1]->id] = 5;
            $plan[$shuffled[2]->id] = 4;

            $remaining = $shuffled->slice(3);
            $leftover = 11; // 6 + 5 + 4 + 11 = 26 total

            foreach ($remaining as $student) {
                if ($leftover <= 0) {
                    break;
                }

                $take = min(rand(1, 3), $leftover);
                $plan[$student->id] = $take;
                $leftover -= $take;
            }

            // If leftover remains (few students), pile onto first student.
            if ($leftover > 0) {
                $plan[$shuffled[0]->id] += $leftover;
            }
        } else {
            // Fallback: few students — split 26 evenly-ish.
            foreach ($shuffled as $i => $student) {
                $plan[$student->id] = ($i === 0) ? 14 : 12;
            }
        }

        // Spread across 3 months (now, -1, -2) so month filter has options.
        $months = collect(range(0, 2))->map(fn ($i) => now()->subMonths($i));
        $monthIndex = 0;

        foreach ($plan as $studentId => $count) {
            for ($i = 0; $i < $count; $i++) {
                $month = $months[$monthIndex % $months->count()];
                $monthIndex++;

                $createdAt = $month->copy()
                    ->startOfMonth()
                    ->addDays(rand(0, $month->daysInMonth - 1))
                    ->addHours(rand(8, 20))
                    ->addMinutes(rand(0, 59));

                Financial::create([
                    'title' => $titles[array_rand($titles)],
                    'description' => $descriptions[array_rand($descriptions)],
                    'user_id' => $studentId,
                    'created_by' => $assistants->random()->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }
}
