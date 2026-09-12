{{-- cases: no days / one day / many days; scanner may be null; created_at may be string or Carbon --}}
@if ($days->isEmpty())
    <div class="flex flex-col items-center justify-center gap-2 py-10 text-center" dir="rtl"
        style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:40px 0; text-align:center;">
        <p class="text-sm text-gray-500 dark:text-gray-400" style="font-size:0.875rem; color:#9ca3af;">
            لا توجد أيام مسجلة.
        </p>
    </div>
@else
    <div class="space-y-4" dir="rtl" style="display:flex; flex-direction:column; gap:16px;">

        {{-- Summary --}}
        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-white/5"
            style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.05); padding:10px 16px; border-radius:8px;">
            <span class="text-sm text-gray-600 dark:text-gray-400" style="font-size:0.875rem; color:#9ca3af;">
                إجمالي الحضور
            </span>
            <span class="text-sm font-semibold text-gray-900 dark:text-white"
                style="font-size:0.875rem; font-weight:600; color:#fff;">
                {{ $days->count() }} يوم
            </span>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10"
            style="border:1px solid rgba(255,255,255,0.1); border-radius:8px; overflow:hidden;">

            {{-- Header row --}}
            <div class="grid grid-cols-[1fr_1fr_1fr_1fr] gap-3 bg-gray-50 px-4 py-2.5 text-xs font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400"
                style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; background:rgba(255,255,255,0.05); padding:10px 16px; font-size:0.75rem; color:#9ca3af;">
                <span>اليوم</span>
                <span>التاريخ</span>
                <span>الوقت</span>
                <span>سجّل بواسطة</span>
            </div>

            {{-- Rows --}}
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($days as $day)
                    @php
                        $date = \Illuminate\Support\Carbon::parse($day->created_at);
                      @endphp
                    <div class="grid grid-cols-[1fr_1fr_1fr_1fr] items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                        style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; align-items:center; padding:12px 16px; font-size:0.875rem; border-top:1px solid rgba(255,255,255,0.08);">
                        <span class="font-medium text-gray-900 dark:text-white" style="font-weight:500; color:#fff;">
                            {{ $date->translatedFormat('l') }}
                        </span>
                        <span class="text-gray-600 dark:text-gray-300" style="color:#d1d5db;">
                            {{ $date->format('Y-m-d') }}
                        </span>
                        <span class="text-gray-600 dark:text-gray-300" style="color:#d1d5db;">
                            {{ $date->format('h:i A') }}
                        </span>
                        <span class="text-gray-500 dark:text-gray-400" style="color:#9ca3af;">
                            {{ $day->scanner?->name ?? '—' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif