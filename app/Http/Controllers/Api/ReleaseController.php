<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublishReleaseRequest;
use App\Models\Release;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReleaseController extends Controller
{
    public function latest(): JsonResponse
    {
        $release = Release::query()
            ->latestRelease()
            ->first();

        if ($release === null) {
            return $this->errorResponse('لا توجد إصدارات متاحة بعد', 404);
        }

        return $this->successResponse($this->payload($release));
    }

    public function publish(PublishReleaseRequest $request): JsonResponse
    {
        $filePath = 'releases/'.$request->string('file_name');

        $release = Release::create([
            'version_code' => $request->integer('version_code'),
            'version_name' => $request->string('version_name'),
            'changelog' => $request->input('changelog'),
            'file_name' => $request->input('file_name'),
            'file_size' => Storage::disk('public')->size($filePath),
            'published_at' => now(),
        ]);

        return $this->successResponse($this->payload($release), 'تم نشر الإصدار بنجاح', 201);
    }

    public function download(): BinaryFileResponse|JsonResponse
    {
        $release = Release::query()
            ->latestRelease()
            ->first();

        if ($release === null) {
            return $this->errorResponse('لا توجد إصدارات متاحة بعد', 404);
        }

        $path = 'releases/'.$release->file_name;

        if (! Storage::disk('public')->exists($path)) {
            return $this->errorResponse('ملف الإصدار غير موجود', 404);
        }

        return response()->download(
            Storage::disk('public')->path($path),
            $release->file_name,
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }

    private function payload(Release $release): array
    {
        return [
            'id' => $release->id,
            'version_code' => $release->version_code,
            'version_name' => $release->version_name,
            'changelog' => $release->changelog,
            'file_name' => $release->file_name,
            'file_size' => $release->file_size,
            'file_url' => Storage::disk('public')->url('releases/'.$release->file_name),
            'published_at' => $release->published_at?->toIso8601String(),
        ];
    }
}
