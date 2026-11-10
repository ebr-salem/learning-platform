{{-- cases: missing record / sent / failed then SMS-rescued / failed outright --}}
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
        $sent = $record->status === \App\Enums\WhatsappLogStatus::Sent;
    @endphp

    <div dir="rtl" style="display:flex; flex-direction:column; gap:20px;">

        {{-- Outcome banner --}}
        <div
            style="display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:8px; font-size:0.875rem; font-weight:500;
                background:{{ $sent ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)' }};
                color:{{ $sent ? '#4ade80' : '#f87171' }};">
            <span>{{ $sent ? 'تم إرسال الرسالة عبر واتساب بنجاح.' : 'فشل إرسال الرسالة عبر واتساب.' }}</span>
            @if (! $sent && $record->sms_fallback_sent)
                <span style="color:#fbbf24;">تم إرسال رسالة SMS احتياطية بنجاح.</span>
            @elseif(! $sent)
                <span style="color:#f87171;">لم تُرسل رسالة SMS احتياطية أيضاً.</span>
            @endif
        </div>

        {{-- Message --}}
        <div>
            <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:6px;">نص الرسالة</p>
            <div
                style="background:rgba(255,255,255,0.05); border-radius:8px; padding:14px 16px; font-size:0.875rem; line-height:1.75; color:#e5e7eb; white-space:pre-wrap; word-break:break-word;">
                {{ $record->message }}
            </div>
        </div>

        {{-- Recipient --}}
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">الطالب</p>
                <p style="font-size:0.875rem; font-weight:500; color:#fff;">
                    {{ $record->student?->name ?? '—' }}
                </p>
            </div>

            <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">ولي الأمر</p>
                <p style="font-size:0.875rem; font-weight:500; color:#fff;">
                    {{ $record->recipient_name ?? '—' }}
                </p>
            </div>
        </div>

        {{-- Technical details --}}
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">الرقم المُدخل</p>
                <p style="font-size:0.875rem; color:#d1d5db; word-break:break-all;">
                    {{ $record->phone ?? '—' }}
                </p>
            </div>

            <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">معرف المحادثة</p>
                <p style="font-size:0.875rem; color:#d1d5db; word-break:break-all; direction:ltr; text-align:right;">
                    {{ $record->chat_id }}
                </p>
            </div>

            <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">الجلسة</p>
                <p style="font-size:0.875rem; color:#d1d5db; direction:ltr; text-align:right;">
                    {{ $record->session ?? '—' }}
                </p>
            </div>

            <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">حالة HTTP</p>
                <p style="font-size:0.875rem; color:#d1d5db;">
                    {{ $record->http_status ?? '—' }}
                </p>
            </div>
        </div>

        {{-- WAHA message id --}}
        <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
            <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">معرف الرسالة من WAHA</p>
            <p style="font-size:0.875rem; color:#d1d5db; word-break:break-all; direction:ltr; text-align:right;">
                {{ $record->message_id ?? '—' }}
            </p>
        </div>

        {{-- Error, only when there is one --}}
        @if (! $sent && filled($record->error))
            <div>
                <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:6px;">رسالة الخطأ</p>
                <div
                    style="background:rgba(239,68,68,0.1); border-radius:8px; padding:14px 16px; font-size:0.875rem; line-height:1.75; color:#fca5a5; white-space:pre-wrap; word-break:break-word;">
                    {{ $record->error }}
                </div>
            </div>
        @endif

        {{-- Timestamp --}}
        <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:12px 16px;">
            <p style="font-size:0.75rem; color:#9ca3af; margin-bottom:4px;">وقت المحاولة</p>
            <p style="font-size:0.875rem; font-weight:500; color:#fff;">
                @if ($created)
                    {{ $created->translatedFormat('l') }} · {{ $created->format('Y-m-d') }} · {{ $created->format('h:i A') }}
                @else
                    —
                @endif
            </p>
        </div>
    </div>
@endif
