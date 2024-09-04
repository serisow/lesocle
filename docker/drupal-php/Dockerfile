ARG BASE_PHP_TAG
FROM wodby/drupal-php:${BASE_PHP_TAG}


USER root

RUN apk --update --no-cache add \
    libgcc \
    libstdc++ \
    musl \
    qt5-qtbase \
    qt5-qtbase-x11 \
    qt5-qtsvg \
    ttf-freefont \
    ttf-dejavu \
    ttf-droid \
    ttf-liberation \
    fontconfig \
    chromium \
    poppler-utils \
    jpeg-dev \
    libpng \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    zlib-dev \
    libxpm-dev \
    freetype-dev \
    jpegoptim \
    pngquant \
    ffmpeg

# Add openssl dependencies for wkhtmltopdf
RUN echo 'http://dl-cdn.alpinelinux.org/alpine/v3.8/main' >> /etc/apk/repositories && \
    apk add --no-cache libcrypto1.0 libssl1.0

# Add wkhtmltopdf
#COPY --from=wkhtmltopdf /bin/wkhtmltopdf /bin/wkhtmltopdf
# Copy xdebug configuration
#COPY ./xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
USER wodby
