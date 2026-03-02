# ─────────────────────────────────────────────────────────────────────────────
# PHP CPU Stress Test — Azure Web App for Containers
# Base: php:8.2-apache (Debian, Apache 2.4 + mod_php)
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.2-apache

# ── Apache tuning ─────────────────────────────────────────────────────────────
# Enable mod_rewrite (needed for .htaccess rules)
RUN a2enmod rewrite

# Allow .htaccess overrides in the web root
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# ── PHP INI overrides ─────────────────────────────────────────────────────────
# Remove the default execution-time cap so stress tests can run their full duration
RUN { \
    echo 'max_execution_time = 600'; \
    echo 'memory_limit = 256M'; \
    echo 'output_buffering = Off'; \
    echo 'implicit_flush = true'; \
    echo 'error_reporting = E_ALL'; \
    echo 'display_errors = On'; \
} > /usr/local/etc/php/conf.d/stress-test.ini

# ── Apache timeout tuning ─────────────────────────────────────────────────────
# Default Apache Timeout is 60s — raise it so long-running stress scripts don't get cut off
RUN echo 'Timeout 700' >> /etc/apache2/apache2.conf

# ── Copy application files ────────────────────────────────────────────────────
COPY index.php      /var/www/html/index.php
COPY cpu-stress.php /var/www/html/cpu-stress.php
COPY .htaccess      /var/www/html/.htaccess
COPY .user.ini      /var/www/html/.user.ini

# ── Permissions ───────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# ── Azure Web App for Containers requirements ─────────────────────────────────
# Azure requires the container to listen on PORT env var (default 80)
EXPOSE 80

# Optional: Azure sets WEBSITES_PORT env var — Apache already listens on 80 by default
# If you need a custom port, uncomment and adjust:
# RUN sed -i 's/Listen 80/Listen ${PORT:-80}/' /etc/apache2/ports.conf

# ── Health check ──────────────────────────────────────────────────────────────
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Apache foreground (default CMD from base image is apache2-foreground)
CMD ["apache2-foreground"]
