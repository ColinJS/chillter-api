FROM php:5.6-apache
MAINTAINER Michał Szczech <michal.szczech@db-team.pl>

# Update sources
RUN apt-get update -y

# Install NodeJS
RUN curl -sL https://deb.nodesource.com/setup_6.x | bash -
RUN apt-get install -y nodejs

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version

# Install Xdebug
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Gulp
RUN npm install -g gulp-cli

# Install text editors
RUN apt-get install -y nano
RUN apt-get install -y vim
RUN apt-get install -y mc

# Install cURL
RUN apt-get install -y libcurl4-openssl-dev

# Install GD dependencies
RUN apt-get install -y libjpeg-dev libpng12-dev

#Setup Virtual Host
RUN rm /etc/apache2/sites-available/000-default.conf
ADD ./vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default.conf

# Enable Apache2 mods
RUN a2enmod rewrite

RUN docker-php-ext-install pdo pdo_mysql && \
    docker-php-ext-install curl && \
    docker-php-ext-install gd && \
    docker-php-ext-configure gd --with-jpeg-dir=/usr/include/ && \
    docker-php-ext-install gd

RUN a2enmod headers

WORKDIR /var/www/project