# Imagen base con PHP y Apache (PHP ya viene instalado aquí)
FROM php:8.3-apache

# Evita advertencias de Composer al correr como root en contenedor
ENV COMPOSER_ALLOW_SUPERUSER=1

# Paquetes del sistema y libs para extensiones PHP
RUN apt-get update && apt-get install -y \
    git unzip \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev \
 && rm -rf /var/lib/apt/lists/*

# Extensiones PHP necesarias y mod_rewrite para CodeIgniter
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mysqli zip gd intl bcmath \
 && docker-php-ext-enable opcache \
 && a2enmod rewrite

WORKDIR /var/www/html

# ---- Instalar el binario de Composer en la imagen final ----
RUN php -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php

# ---- Instalar dependencias en build (vendor/) ----
# Copiamos sólo composer.json y (si existe) composer.lock para cachear capas
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-ansi

# Copiamos el resto del código
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Vhost y entrypoint
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && a2ensite 000-default.conf

ENV CI_ENV=development
EXPOSE 80
CMD ["/entrypoint.sh"]
