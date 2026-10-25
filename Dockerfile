FROM php:8.2-apache

# Instalar las librerías necesarias para PostgreSQL y habilitar pdo_pgsql
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite

# Copiar todo el código del proyecto al directorio web de Apache
COPY . /var/www/html/

# Exponer el puerto estándar HTTP
EXPOSE 80