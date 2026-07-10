<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سجلات التشغيل</title>
    <style>
        body {
            font-family: 'freeserif', 'sans-serif';
            direction: rtl;
            text-align: right;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        h2 {
            text-align: center;
            color: #333;
        }
    </style>
</head>
<body>
    <h2>سجلات التشغيل - SabaControl</h2>
    <p>تاريخ الاستخراج: {{ date('Y-m-d H:i:s') }}</p>
    
    <table>
        <thead>
            <tr>
                <th>المعرّف</th>
                <th>اسم الإعلان</th>
                <th>الشاشة العارضة</th>
                <th>مدة العرض</th>
                <th>وقت التشغيل</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->log_id }}</td>
                    <td>{{ $log->advertisement->title ?? 'غير معروف' }}</td>
                    <td>{{ $log->screen->screen_name ?? 'غير معروف' }}</td>
                    <td>{{ $log->advertisement->duration ?? 15 }} ثانية</td>
                    <td dir="ltr">{{ \Carbon\Carbon::parse($log->played_at)->format('Y-m-d h:i A') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
