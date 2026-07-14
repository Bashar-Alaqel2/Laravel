# استخدام نسخة CLI الخفيفة والسريعة جداً (بدون خادم Apache لتجنب أي أعطال أو أخطاء MPM)
FROM php:8.4-cli

# تثبيت الحزم الأساسية
RUN apt-get update && apt-get install -y libpq-dev zip unzip

# تفعيل إضافات قاعدة البيانات، والأهم: تفعيل إضافة pcntl لتمكين العمال (Workers)
RUN docker-php-ext-install pdo pdo_pgsql pcntl

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# نسخ ملفات المشروع بالكامل إلى داخل الحاوية
COPY . /var/www/html

# تحديد مسار العمل
WORKDIR /var/www/html

# إعطاء صلاحيات الكتابة كإجراء احتياطي
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تحميل حزم Laravel
RUN composer install --optimize-autoloader --no-dev

# تحديد عدد العمال (Workers) لتمكين السيرفر من معالجة عدة طلبات في وقت واحد بدون أن يختنق (CORS fix)
ENV PHP_CLI_SERVER_WORKERS=10

# تشغيل سيرفر Laravel الداخلي السريع، وربطه بالمنفذ الديناميكي الخاص بـ Railway
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
