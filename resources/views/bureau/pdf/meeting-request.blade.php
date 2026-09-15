<!doctype html>
<html dir="rtl" lang="dv">
<head>
    <meta charset="utf-8">
    @include('bureau.pdf.partials.fonts')
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Faruma', sans-serif;
            margin: 0;
            padding: 40px;
            color: #1a1a1a;
        }

        h1 {
            font-family: 'Mv Galan Normal', 'Faruma', sans-serif;
            font-size: 22px;
            margin: 0 0 24px;
        }

        .body-text {
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 24px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td {
            padding: 6px 0;
            font-size: 13px;
            vertical-align: top;
        }

        td.label {
            color: #555;
            width: 140px;
        }
    </style>
</head>
<body>
    <h1>{{ __('bureau.meeting.pdf.request_title') }}</h1>

    <p class="body-text">{{ __('bureau.meeting.pdf.request_body') }}</p>

    <table>
        <tr>
            <td class="label">{{ __('bureau.meeting.field.name') }}</td>
            <td>{{ $meeting->name }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('bureau.meeting.field.type') }}</td>
            <td>{{ $meeting->type_label }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('bureau.meeting.field.scheduled_at') }}</td>
            <td dir="rtl">{{ \App\Support\DhivehiDate::format($meeting->scheduled_at) }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('bureau.meeting.field.place') }}</td>
            <td>{{ $meeting->place }}</td>
        </tr>
    </table>
</body>
</html>
