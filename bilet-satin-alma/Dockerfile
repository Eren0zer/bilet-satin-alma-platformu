# Dockerfile
FROM php:8.2-apache

# Gerekli PHP eklentileri
RUN apt-get update && apt-get install -y libsqlite3-dev \
  && docker-php-ext-install pdo pdo_sqlite \
  && a2enmod rewrite

# Varsayılan siteyi açıkça public/ köküne yönlendir ve izinleri ver
RUN printf '%s\n' \
  '<VirtualHost *:80>' \
  '  ServerAdmin webmaster@localhost' \
  '  DocumentRoot /var/www/html/public' \
  '  <Directory /var/www/html/public>' \
  '    AllowOverride All' \
  '    Require all granted' \
  '    Options Indexes FollowSymLinks' \
  '  </Directory>' \
  '  ErrorLog ${APACHE_LOG_DIR}/error.log' \
  '  CustomLog ${APACHE_LOG_DIR}/access.log combined' \
  '</VirtualHost>' \
  > /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . /var/www/html

# data klasörü yazılabilir olsun (SQLite dosyasını burada tutuyoruz)
RUN mkdir -p /var/www/html/data \
  && chown -R www-data:www-data /var/www/html/data \
  && chmod -R 775 /var/www/html/data
