# استخدام نسخة CLI الخفيفة جداً (بدون خادم Apache لتجنب أي أعطال أو أخطاء MPM)
FROM php:8.4-cli

# تثبيت الحزم الأساسية وإضافات قاعدة البيانات PostgreSQL
RUN apt-get update && apt-get install -y \
# استخدام نسخة PHP مع خادم Apache
FROM php:8.4-apache

# تثبيت الحزم المطلوبة وتفعيل إضافات قاعدة البيانات
RUN apt-get update && apt-get install -y libpq-dev zip unzip \
    && docker-php-ext-install pdo pdo_pgsql

# إصلاح مشكلة الـ MPM الشهيرة في Apache مع PHP 8.4
RUN a2dismod mpm_event && a2enmod mpm_prefork

# تمكين mod_rewrite
RUN a2enmod rewrite

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# نسخ ملفات المشروع بالكامل إلى الحاوية
COPY . /var/www/html

# تغيير المنفذ ليتوافق مع المنفذ الديناميكي الخاص بـ Railway
RUN sed -i "s/80/\${PORT:-8000}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# إعداد public كمسار افتراضي لـ Apache
RUN sed -i "s|/var/www/html|/var/www/html/public|g" /etc/apache2/sites-available/000-default.conf

# إعطاء صلاحيات الكتابة للمجلدات المهمة في Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تحميل حزم Laravel
RUN composer install --optimize-autoloader --no-dev

# بدء تشغيل Apache في الواجهة الأمامية
CMD ["apache2-foreground"]
