<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
        ]);
    }

    private function stageApk(string $name = 'app-v1.0.0.apk'): void
    {
        Storage::disk('public')->putFileAs('releases', UploadedFile::fake()->create($name, 1024), $name);
    }

    public function test_latest_returns_404_when_no_release_exists(): void
    {
        $this->getJson('/api/v1/app/releases/latest')
            ->assertStatus(404);
    }

    public function test_publish_requires_authentication(): void
    {
        $this->postJson('/api/v1/app/releases')
            ->assertStatus(401);
    }

    public function test_publish_requires_admin_role(): void
    {
        Storage::fake('public');
        $this->stageApk();

        $user = User::factory()->create(['role' => UserRole::Student]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/app/releases', [
                'version_code' => 1,
                'version_name' => '1.0.0',
                'file_name' => 'app-v1.0.0.apk',
            ])
            ->assertStatus(403);
    }

    public function test_publish_rejects_apk_that_is_not_staged(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/app/releases', [
                'version_code' => 1,
                'version_name' => '1.0.0',
                'file_name' => 'missing.apk',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.file_name.0', 'ملف APK غير موجود في مجلد الإصدارات');
    }

    public function test_admin_can_publish_a_release(): void
    {
        Storage::fake('public');
        $this->stageApk();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/app/releases', [
                'version_code' => 1,
                'version_name' => '1.0.0',
                'changelog' => 'First release',
                'file_name' => 'app-v1.0.0.apk',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version_code', 1)
            ->assertJsonPath('data.file_url', '/storage/releases/app-v1.0.0.apk');

        $this->assertDatabaseHas('releases', [
            'version_code' => 1,
            'file_name' => 'app-v1.0.0.apk',
        ]);
    }

    public function test_version_code_must_be_unique(): void
    {
        Storage::fake('public');
        $this->stageApk('first.apk');
        $this->stageApk('second.apk');

        Release::create([
            'version_code' => 1,
            'version_name' => '1.0.0',
            'file_name' => 'first.apk',
            'file_size' => 1024,
            'published_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/app/releases', [
                'version_code' => 1,
                'version_name' => '1.0.1',
                'file_name' => 'second.apk',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.version_code.0', 'رقم الإصدار مستخدم من قبل');
    }

    public function test_latest_returns_the_most_recent_published_release(): void
    {
        Storage::fake('public');
        $this->stageApk('old.apk');
        $this->stageApk('new.apk');

        Release::create([
            'version_code' => 1,
            'version_name' => '1.0.0',
            'file_name' => 'old.apk',
            'file_size' => 1024,
            'published_at' => now()->subDay(),
        ]);

        Release::create([
            'version_code' => 2,
            'version_name' => '2.0.0',
            'file_name' => 'new.apk',
            'file_size' => 2048,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/app/releases/latest')
            ->assertStatus(200)
            ->assertJsonPath('data.version_code', 2)
            ->assertJsonPath('data.version_name', '2.0.0');
    }

    public function test_download_streams_the_latest_apk(): void
    {
        Storage::fake('public');
        $this->stageApk('app-v1.0.0.apk');

        $release = Release::create([
            'version_code' => 1,
            'version_name' => '1.0.0',
            'file_name' => 'app-v1.0.0.apk',
            'file_size' => 1024,
            'published_at' => now(),
        ]);

        $this->get('/api/v1/app/releases/latest/download')
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.android.package-archive');
    }
}
