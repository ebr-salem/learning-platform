<?php

namespace Tests\Feature;

use App\Enums\FinancialType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Financial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReportsTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->withGroup()->create(['role' => UserRole::Student]);
    }

    private function assistant(): User
    {
        return User::factory()->assistant()->create();
    }

    public function test_reports_requires_authentication(): void
    {
        $this->getJson('/api/v1/student/reports')
            ->assertStatus(401);
    }

    public function test_reports_requires_student_role(): void
    {
        $this->actingAs($this->assistant(), 'sanctum')
            ->getJson('/api/v1/student/reports')
            ->assertStatus(403);
    }

    public function test_reports_returns_attendance_and_financials_summary(): void
    {
        $assistant = $this->assistant();
        $student = $this->student();

        Attendance::create([
            'student_id' => $student->id,
            'scanned_by' => $assistant->id,
            'created_at' => now(),
        ]);

        $financial = Financial::factory()->create([
            'user_id' => $student->id,
            'created_by' => $assistant->id,
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/v1/student/reports')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.student.student_code', $student->studentProfile->student_code)
            ->assertJsonPath('data.attendance.total', 1)
            ->assertJsonCount(1, 'data.attendance.records')
            ->assertJsonPath('data.financials.total', 1)
            ->assertJsonPath('data.financials.records.0.id', $financial->id)
            ->assertJsonPath('data.financials.records.0.type_label', 'الشهرية');
    }

    public function test_reports_filters_monthly_financials_by_month(): void
    {
        $assistant = $this->assistant();
        $student = $this->student();

        Financial::factory()->create([
            'user_id' => $student->id,
            'created_by' => $assistant->id,
            'title' => '5',
        ]);

        $september = Financial::factory()->create([
            'user_id' => $student->id,
            'created_by' => $assistant->id,
            'title' => '9',
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/v1/student/reports?month=9')
            ->assertStatus(200)
            ->assertJsonPath('data.financials.total', 1)
            ->assertJsonPath('data.financials.records.0.id', $september->id)
            ->assertJsonPath('data.financials.records.0.title_label', '9 - سبتمبر');
    }

    public function test_reports_month_filter_keeps_other_type_records(): void
    {
        $assistant = $this->assistant();
        $student = $this->student();

        $other = Financial::factory()->create([
            'user_id' => $student->id,
            'created_by' => $assistant->id,
            'type' => FinancialType::Other,
            'title' => 'رسوم اشتراك',
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/v1/student/reports?month=3')
            ->assertStatus(200)
            ->assertJsonPath('data.financials.total', 1)
            ->assertJsonPath('data.financials.records.0.id', $other->id)
            ->assertJsonPath('data.financials.records.0.type_label', 'أخرى');
    }

    public function test_reports_filters_attendance_and_financials_by_year(): void
    {
        $assistant = $this->assistant();
        $student = $this->student();

        Attendance::create([
            'student_id' => $student->id,
            'scanned_by' => $assistant->id,
            'created_at' => now()->subYear(),
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'scanned_by' => $assistant->id,
            'created_at' => now(),
        ]);

        Financial::factory()->create([
            'user_id' => $student->id,
            'created_by' => $assistant->id,
            'created_at' => now()->subYear(),
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/v1/student/reports?year='.now()->year)
            ->assertStatus(200)
            ->assertJsonPath('data.attendance.total', 1)
            ->assertJsonPath('data.financials.total', 0);
    }

    public function test_reports_rejects_invalid_filters(): void
    {
        $this->actingAs($this->student(), 'sanctum')
            ->getJson('/api/v1/student/reports?month=13&year=abc')
            ->assertStatus(422);
    }
}
