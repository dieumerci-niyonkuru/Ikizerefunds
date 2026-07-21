FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql fileinfo gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

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
