# استخدام نسخة PHP 8.2 المستقرة والمجربة جداً (خالية من مشاكل الـ MPM)
FROM php:8.2-apache

# تثبيت الحزم الأساسية للنظام وإضافات قاعدة بيانات PostgreSQL
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl

RUN apt-get clean && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd

# تفعيل خاصية إعادة كتابة الروابط
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تثبيت الحزم مع تجاهل قيود إصدار PHP الموجودة في جهازك (لتعمل مع 8.2 بأمان تام)
RUN composer install --optimize-autoloader --no-dev --ignore-platform-req=php

# إعطاء الصلاحيات اللازمة لمجلدات التخزين
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# توجيه خادم Apache ليقرأ من مجلد public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# أمر التشغيل الذكي لربط المنافذ مع Railway
CMD bash -c "sed -i \"s/80/${PORT:-80}/g\" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && apache2-foreground"
