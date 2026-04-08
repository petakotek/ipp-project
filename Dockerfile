FROM python:3.14-slim-bookworm AS check

RUN apt-get update && apt-get install -y --no-install-recommends \
    php-cli \
    php-xml \
    php-mbstring \
    unzip \
    curl \
    bash \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY tester/requirements.txt /tmp/requirements.txt
COPY tester/requirements-dev.txt /tmp/requirements-dev.txt

RUN pip install --no-cache-dir -r /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements-dev.txt

COPY int/composer.json /tmp/composer.json
COPY int/composer.lock /tmp/composer.lock

RUN mkdir -p /src/vendor
RUN cd /tmp && composer install --no-interaction --no-progress && mv vendor /src

ENTRYPOINT ["/bin/bash"]