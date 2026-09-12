{{-- cases: missing record / full details; dates may be string or Carbon; relations may be null --}}
@if (empty($record ?? null))
    <div dir="rtl"
        style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:40px 0; text-align:center;">
        <p style="font-size:0.875rem; color:#9ca3af;">
            لا يوجد سجل لعرضه.
        </p>
    </div>
@else
    @php
        $created = $record->created_at ? \Illuminate\Support\Carbon::parse($record->created_at) : null;
        $studentName = $record->user?->name ?? '—';
        $creatorName = $record->creator?->name ?? $record->createdBy?->name ?? '—';
    @endphp

    <div dir="rtl" style="display:flex; flex-direction:column; gap:20px;">

        {{-- Title --}}
        <div>
            <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">العنوان</p>
            <p style="font-size:1rem; font-weight:600; color:#fff; line-height:1.5;">
                {{ $record->title ?? '—' }}
            </p>
        </div>

        {{-- Student + Creator side by side --}}
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">الطالب</p>
                <p style="font-size:0.875rem; font-weight:500; color:#fff;">
                    {{ $studentName }}
                </p>
            </div>

            <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">أُنشئ بواسطة</p>
                <p style="font-size:0.875rem; font-weight:500; color:#fff;">
                    {{ $creatorName }}
                </p>
            </div>
        </div>

        {{-- Created at --}}
        <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
            <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">تاريخ الإنشاء</p>
            <p style="font-size:0.875rem; font-weight:500; color:#fff;">
                @if ($created)
                    {{ $created->translatedFormat('l') }} · {{ $created->format('Y-m-d') }} · {{ $created->format('h:i A') }}
                @else
                    —
                @endif
            </p>
        </div>

        {{-- Description --}}
        <div>
            <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:6px;">الوصف</p>
            <div
                style="background:rgba(255,255,255,0.05); border-radius:8px; padding:14px 16px; font-size:0.875rem; line-height:1.75; color:#e5e7eb; white-space:pre-wrap; word-break:break-word;">
                {{ $record->description ?? '—' }}
            </div>
        </div>
    </div>
@endif