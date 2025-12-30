FROM php:8.2-apache

# System-Pakete installieren
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP-Erweiterungen installieren
RUN docker-php-ext-install pdo pdo_sqlite

# Apache mod_rewrite aktivieren
RUN a2enmod rewrite

# Apache-Konfiguration für .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Arbeitsverzeichnis setzen
WORKDIR /var/www/html

# Anwendungsdateien kopieren
COPY config/ /var/www/config/
COPY src/ /var/www/src/
COPY public/ /var/www/html/

# Verzeichnisse für Daten und Datenbank erstellen
RUN mkdir -p /var/www/data/files /var/www/database

# Berechtigungen setzen
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 777 /var/www/data /var/www/database

# Config-Pfade für Container anpassen
RUN sed -i "s|__DIR__ . '/../data/files'|'/var/www/data/files'|g" /var/www/config/config.php \
    && sed -i "s|__DIR__ . '/../database/users.db'|'/var/www/database/users.db'|g" /var/www/config/config.php

# Port freigeben
EXPOSE 80

# Apache im Vordergrund starten
CMD ["apache2-foreground"]
