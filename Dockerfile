FROM php:8.2-apache
RUN apt-get update && apt-get install --no-install-recommends -y \
	libyaml-dev \
	libpng-dev \
	libcurl3-dev \
	libssl-dev \
	libzip-dev \
	gnupg \
	ca-certificates \
	software-properties-common

RUN docker-php-ext-install -j$(nproc) mysqli phar gd curl \
    && echo "phar.readonly=0" > /usr/local/etc/php/conf.d/phar.ini

RUN pecl install yaml zip apcu
RUN docker-php-ext-enable yaml zip apcu
RUN a2enmod rewrite \
    && usermod -u 1000 www-data \
    && groupmod -g 33 www-data

RUN a2enmod headers

RUN apt install wget
RUN wget -O - https://getcomposer.org/installer | php
RUN chmod +x composer.phar && mv composer.phar /usr/local/bin/composer

ADD entry.php /poggit/entry.php
ADD assets /poggit/assets
ADD composer.json /poggit/composer.json
ADD composer.lock /poggit/composer.lock
ADD js /poggit/js
ADD res /poggit/res
ADD src /poggit/src
ADD stub/.htaccess /var/www/html/.htaccess
RUN echo "<?php include '/poggit/entry.php'; " >/var/www/html/index.php

WORKDIR /poggit
RUN cat composer.json && composer install --no-dev
