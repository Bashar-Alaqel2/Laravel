<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تنظيف مساحة السيرفر من ملفات الإعلانات المحذوفة بعد 30 يوم
Schedule::command('ads:clean-deleted-files --days=30')->dailyAt('02:00');

// تنظيف سجلات التشغيل القديمة لتخفيف قاعدة البيانات
Schedule::command('logs:cleanup --days=30')->dailyAt('03:00');

// مراقبة الشاشات المنقطعة وإيقاف إعلاناتها مؤقتاً
Schedule::command('screens:check-downtime')->everyFiveMinutes();
