# استخدام نسخة Apache الرسمية والمستقرة لـ Laravel
FROM php:8.2-apache

# تثبيت الحزم الأساسية
RUN apt-get update && apt-get install -y libpq-dev zip unzip libpng-dev

# تفعيل إضافات قاعدة البيانات
RUN docker-php-ext-install pdo pdo_pgsql gd

# تفعيل ميزة Rewrite في خادم Apache (مهم جداً لـ Laravel)
RUN a2enmod rewrite

# تغيير مجلد Apache الافتراضي ليكون مجلد public الخاص بـ Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# نسخ ملفات المشروع بالكامل إلى داخل الحاوية
COPY . /var/www/html

# تحديد مسار العمل
WORKDIR /var/www/html

# تحميل حزم Laravel
RUN composer install --optimize-autoloader --no-dev

# إعطاء صلاحيات الكتابة كإجراء احتياطي لملفات التخزين المؤقت
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تحديد المنفذ الذي سيعمل عليه Apache (يتماشى مع Railway)
EXPOSE 8080

# تغيير منفذ Apache إلى 8080
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf
RUN sed -i 's/:80/:8080/g' /etc/apache2/sites-available/000-default.conf

# تشغيل خادم Apache في الواجهة
CMD ["apache2-foreground"]
