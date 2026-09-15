<!doctype html>
<html dir="rtl" lang="dv">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; direction: rtl; text-align: right;">
    <p>{{ __('bureau.mail.meeting_scheduled.greeting') }}</p>
    <p>{{ __('bureau.mail.meeting_scheduled.body') }}</p>
    <p>
        <strong>{{ $meeting->name }}</strong><br>
        {{ $meeting->type_label }}<br>
        {{ $meeting->scheduled_at->format('Y-m-d H:i') }}
    </p>
</body>
</html>
