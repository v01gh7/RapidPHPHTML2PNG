FROM php:7.4-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    zip \
    unzip \
    wget \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    curl \
    gd \
    mbstring \
    pdo \
    pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

# Install wkhtmltoimage (optional - best quality rendering)
# This is optional and may fail - the app will fall back to GD
RUN wget -q https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.buster_amd64.deb \
    && dpkg -i wkhtmltox_0.12.6.1-3.buster_amd64.deb || apt-get install -f -y || true \
    && (rm -f wkhtmltox_0.12.6.1-3.buster_amd64.deb || true) || true

# Create output directory
RUN mkdir -p /var/www/html/assets/media/rapidhtml2png \
    && chown -R www-data:www-data /var/www/html/assets

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
