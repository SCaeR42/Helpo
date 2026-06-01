FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy only composer files for dependency installation
COPY composer.json ./

# Install dependencies
RUN composer config --global --no-interaction allow-plugins.php-http/discovery true && \
    composer install --no-interaction --optimize-autoloader --ignore-platform-reqs --no-security-blocking

# Create logs and docs directories (will be mounted via volumes)
RUN mkdir -p logs docs && chmod 777 logs docs

# Expose port
EXPOSE 8000

# Default command
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
