# استخدام نسخة PHP مع خادم Apache
FROM php:8.4-apache

# تثبيت الحزم الأساسية للنظام التي يحتاجها Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl

# تنظيف ذاكرة التخزين المؤقت لتخفيف حجم الحاوية
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# تثبيت إضافات PHP الضرورية للتعامل مع قواعد البيانات والصور
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# تفعيل خاصية إعادة كتابة الروابط في خادم Apache
RUN a2enmod rewrite

# تحديد مجلد العمل داخل السيرفر
WORKDIR /var/www/html

# نسخ ملفات المشروع من جهازك إلى السيرفر
COPY . /var/www/html

# جلب أداة Composer لتثبيت حزم Laravel
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تثبيت حزم المشروع (تخطي حزم التطوير لتقليل الحجم)
RUN composer install --optimize-autoloader --no-dev

# إعطاء الصلاحيات اللازمة لمجلدات التخزين
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# توجيه خادم Apache ليقرأ من مجلد public بدلاً من المجلد الرئيسي
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# فتح المنفذ رقم 80 ليستقبل الطلبات
EXPOSE 80
