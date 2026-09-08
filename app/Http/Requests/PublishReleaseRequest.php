<?php

namespace App\Http\Requests;

use App\Models\Release;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PublishReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // dd($this->availableApkFiles());
        return $this->user()?->role->value === 'admin';
    }

    public function rules(): array
    {
        return [
            'version_code' => ['required', 'integer', Rule::unique(Release::class)],
            'version_name' => ['required', 'string', 'max:255'],
            'changelog' => ['nullable', 'string'],
            'file_name' => ['required', 'string', Rule::in($this->availableApkFiles())],
        ];
    }

    public function messages(): array
    {
        return [
            'version_code.required' => 'رقم الإصدار مطلوب',
            'version_code.unique' => 'رقم الإصدار مستخدم من قبل',
            'version_name.required' => 'اسم الإصدار مطلوب',
            'file_name.required' => 'اسم ملف APK مطلوب',
            'file_name.in' => 'ملف APK غير موجود في مجلد الإصدارات',
        ];
    }

    private function availableApkFiles(): array
    {
        $files = Storage::disk('public')->files('releases');

        return collect($files)
            ->filter(fn (string $path) => str_ends_with($path, '.apk'))
            ->map(fn (string $path) => basename($path))
            ->values()
            ->all();
    }
}
