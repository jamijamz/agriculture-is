FROM php:8.2-apache

# Install system dependencies and PostgreSQL PDO extensions required for Supabase
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Enable Apache mod_rewrite for clean URLs/routing if needed
RUN a2enmod rewrite

# Copy all project files into the container web root
COPY . /var/www/html/

# Set the working directory
WORKDIR /var/www/html/

# Expose port 80 so Render can route incoming web traffic
EXPOSE 80
