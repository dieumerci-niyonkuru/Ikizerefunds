FROM php:8.2-apache

# Install system dependencies for GD extension
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql fileinfo gd \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and disable conflicting MPM
RUN a2enmod rewrite && a2dismod mpm_event && a2enmod mpm_prefork

# Copy project files
COPY . /var/www/html/

# Set permissions for upload directories
RUN mkdir -p /var/www/html/assets/uploads/documents \
    && mkdir -p /var/www/html/assets/uploads/member_documents \
    && chown -R www-data:www-data /var/www/html/assets/uploads

# Apache listens on the port Railway assigns via $PORT
ENV PORT=8080
RUN sed -i "s/80/${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE ${PORT}

# Run setup then start Apache
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
