# استخدام نسخة CLI الخفيفة جداً (بدون خادم Apache لتجنب أي أعطال أو أخطاء MPM)
FROM php:8.4-cli

# تثبيت الحزم الأساسية وإضافات قاعدة البيانات PostgreSQL
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

WORKDIR /var/www/html
COPY . /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تثبيت الحزم (بدون تجاهل الإصدار لأننا نستخدم 8.4 المناسب لمشروعك)
RUN composer install --optimize-autoloader --no-dev

# تشغيل سيرفر Laravel الداخلي السريع، وربطه بالمنفذ الديناميكي الخاص بـ Railway
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
