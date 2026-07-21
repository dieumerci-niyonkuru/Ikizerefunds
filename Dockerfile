FROM php:8.2-cli

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

# Copy project files
COPY . /var/www/html/
WORKDIR /var/www/html

# Set permissions for upload directories
RUN mkdir -p assets/uploads/documents \
    && mkdir -p assets/uploads/member_documents

# Railway sets $PORT at runtime
ENV PORT=8080
EXPOSE 8080

# Run setup then start PHP built-in server
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
