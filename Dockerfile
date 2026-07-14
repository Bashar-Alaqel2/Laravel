# استخدام نسخة PHP مع خادم Apache
FROM php:8.4-apache

# تثبيت الحزم الأساسية للنظام التي يحتاجها Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl

# تنظيف ذاكرة التخزين المؤقت لتخفيف حجم الحاوية
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# تثبيت إضافات PHP الضرورية للتعامل مع قواعد البيانات والصور
RUN docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd

# حل مشكلة تعارض MPM (More than one MPM loaded) إجبارياً
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork || true

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

# أمر التشغيل: ربط المنفذ بشكل ديناميكي مع Railway لتجنب أخطاء الاتصال
CMD sh -c "sed -i 's/80/${PORT:-80}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && docker-php-entrypoint apache2-foreground"
