FROM php:8.2-apache

# Instalar extensiones para PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite

# Reemplazar la configuración por defecto de Apache con la nuestra
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copiar todo el código del proyecto
COPY . /var/www/html/

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80