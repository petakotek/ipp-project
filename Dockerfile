FROM python:3.14-slim-bookworm AS check

RUN apt-get update && apt-get install -y --no-install-recommends \
    php-cli \
    php-xml \
    php-mbstring \
    unzip \
    curl \
    bash \
    gcc \
    libxml2-dev \
    libxslt-dev \
    zlib1g-dev \
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

RUN echo '#!/bin/bash\n\
\
chmod +x /src/int/phpstan /src/int/phpcs 2>/dev/null\n\
chmod +x /src/tester/mypy 2>/dev/null\n\
chmod +x /src/tester/ruff 2>/dev/null\n\
\
exec "$@"' > /entrypoint.sh && chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

CMD ["/bin/bash"]


FROM php:8.5-cli-bookworm AS runtime

WORKDIR /interpreter
COPY int/ .

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-interaction --no-progress --no-dev --optimize-autoloader

ENTRYPOINT ["/usr/local/bin/php", "src/solint.php"]

FROM python:3.14-slim-bookworm AS test

WORKDIR /int
COPY /int .

RUN apt-get update && apt-get install -y --no-install-recommends \
    php-cli \
    php-xml \
    php-mbstring \
    curl \
    unzip \
    gcc \
    libxml2-dev \
    libxslt-dev \
    zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-interaction --no-progress --no-dev --optimize-autoloader

WORKDIR /test
COPY tester/ .

RUN pip install --no-cache-dir -r requirements.txt

WORKDIR /test/src

ENTRYPOINT ["/usr/local/bin/python3.14", "tester.py"]