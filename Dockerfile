# استخدام نسخة CLI الخفيفة جداً (لتجنب مشاكل Apache MPM المعقدة)
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

# تحديد عدد العمال (Workers) لتمكين السيرفر من معالجة عدة طلبات في وقت واحد بدون أن يختنق
ENV PHP_CLI_SERVER_WORKERS=10

# تحديد المنفذ الافتراضي الثابت لـ Railway
EXPOSE 8080

# تشغيل خادم PHP الداخلي مباشرة (بدون artisan serve) لكي يتم تفعيل الـ Workers بنجاح وحل مشكلة 502
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public/"]
