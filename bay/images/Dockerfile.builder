FROM uselagoon/php-7.4-cli-drupal:latest
ARG GOJQ_VERSION=0.12.4

ENV WEBROOT=docroot \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/.composer/cache

# Temporarily use a fork of dockerize as the binary download is broken.
# @see https://github.com/jwilder/dockerize/issues/166
# The dpc-sdp fork can be removed once this issue is resolved.
#ENV DOCKERIZE_VERSION v0.6.1
#RUN wget https://github.com/jwilder/dockerize/releases/download/$DOCKERIZE_VERSION/dockerize-alpine-linux-amd64-$DOCKERIZE_VERSION.tar.gz \
#    && tar -C /usr/local/bin -xzvf dockerize-alpine-linux-amd64-$DOCKERIZE_VERSION.tar.gz \
#    && rm dockerize-alpine-linux-amd64-$DOCKERIZE_VERSION.tar.gz
RUN wget -O /usr/local/bin/dockerize https://github.com/dpc-sdp/dockerize/releases/download/v0.6.1/dockerize_amd64_linux && \
    chmod +x /usr/local/bin/dockerize

RUN apk update \
    && apk del npm nodejs nodejs-current yarn \
    && apk add nodejs-npm patch rsync redis --no-cache --repository http://dl-3.alpinelinux.org/alpine/v3.7/main/

# Add MySQL client configuration.
COPY php/mariadb-client.cnf /etc/my.cnf.d/
RUN fix-permissions /etc/my.cnf.d/

# Install gojq.
RUN curl -L https://github.com/itchyny/gojq/releases/download/v${GOJQ_VERSION}/gojq_v${GOJQ_VERSION}_linux_amd64.tar.gz --output /tmp/gojq_v${GOJQ_VERSION}_linux_amd64.tar.gz && \
    tar -C /tmp -xvf /tmp/gojq_v${GOJQ_VERSION}_linux_amd64.tar.gz && \
    chmod +x /tmp/gojq_v${GOJQ_VERSION}_linux_amd64/gojq && \
    mv /tmp/gojq_v${GOJQ_VERSION}_linux_amd64/gojq /usr/local/bin

# Update to Composer 2
RUN composer global remove hirak/prestissimo \
    && composer self-update --2

# Add common drupal config.
RUN mkdir /bay
COPY docker/services.yml /bay
COPY docker/redis-unavailable.services.yml /bay
COPY docker/redis-cluster.services.yml /bay
COPY docker/redis-single.services.yml /bay
COPY docker/settings.php /bay
