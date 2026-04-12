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

RUN printf '#!/bin/bash\nchmod +x /src/int/phpstan /src/int/phpcs 2>/dev/null\nchmod +x /src/tester/mypy /src/tester/ruff 2>/dev/null\n' > /setup.sh && chmod +x /setup.sh

ENTRYPOINT ["/bin/bash"]


FROM php:8.5-cli-bookworm AS runtime

WORKDIR /int
COPY int/ .

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-interaction --no-progress --no-dev --optimize-autoloader

ENTRYPOINT ["/usr/local/bin/php", "src/solint.php"]

FROM runtime AS test

COPY --from=python:3.14-slim-bookworm /usr/local/bin/python3.14 /usr/local/bin/python3.14
COPY --from=python:3.14-slim-bookworm /usr/local/lib/python3.14 /usr/local/lib/python3.14
COPY --from=python:3.14-slim-bookworm /usr/local/lib/libpython3.14.so.1.0 /usr/local/lib/libpython3.14.so.1.0
COPY --from=python:3.14-slim-bookworm /usr/local/bin/pip3 /usr/local/bin/pip3
COPY --from=python:3.14-slim-bookworm /usr/local/include/python3.14 /usr/local/include/python3.14

RUN ln -s /usr/local/bin/php /usr/bin/php
RUN ln -s /usr/local/bin/python3.14 /usr/bin/python

RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    libxml2-dev \
    libxslt-dev \
    zlib1g-dev \
    wget \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /tester
COPY tester/ .

RUN pip3 install --no-cache-dir -r requirements.txt

WORKDIR /tester/src

ENTRYPOINT ["/usr/local/bin/python3.14", "tester.py"]