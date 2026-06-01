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

# Copy composer files
COPY composer.json ./

# Install dependencies
RUN composer config --global --no-interaction allow-plugins.php-http/discovery true && \
    composer install --no-interaction --optimize-autoloader --ignore-platform-reqs --no-security-blocking

# Copy application files
COPY . .

# Create logs directory
RUN mkdir -p logs && chmod 777 logs

# Expose port
EXPOSE 8000

# Default command
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
