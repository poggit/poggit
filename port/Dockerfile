FROM php:7.3
RUN apt update && apt install -y mysql-client
RUN docker-php-ext-install mysqli
ADD port-old.php /port.php
