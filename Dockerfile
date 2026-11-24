FROM php:8.3-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && mkdir -p /var/www/html/public \
    && printf '%s\n' '<?php header("Content-Type: application/json"); echo json_encode(["ok" => true, "service" => "MDRRMO Incidents API"]);' > /var/www/html/public/index.php
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
# Initialize the empty PostgreSQL/PostGIS schema before serving requests.
CMD ["sh", "-c", "php /var/www/html/src/migrate.php && exec apache2-foreground"]
