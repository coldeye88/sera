FROM php:7.3-fpm-stretch

# Container containing php-fpm and php-cli to run and interact with eZ Platform and other Symfony projects
#
# It has two modes of operation:
# - (run.sh cmd) [default] Reconfigure eZ Platform/Publish based on provided env variables and start php-fpm
# - (bash|php|composer) Allows to execute composer, php or bash against the image

# Set defaults for variables used by run.sh
ENV COMPOSER_HOME=/root/.composer

# Get packages that we need in container
RUN apt-get update -q -y \
    && apt-get install -q -y --no-install-recommends \
        ca-certificates \
        curl \
        acl \
        sudo \
# Disable expired Let's Encrypt certificate
    && sed -i '/mozilla\/DST_Root_CA_X3.crt/ s/./!&/' /etc/ca-certificates.conf \
    && update-ca-certificates --verbose \
# Needed for the php extensions we enable below
    && apt-get install -q -y --no-install-recommends \
        libfreetype6 \
        libjpeg62-turbo \
        libxpm4 \
        libpng16-16 \
        libicu57 \
        libxslt1.1 \
        libmemcachedutil2 \
        libzip4 \
        imagemagick \
        libpq5 \ 
# git & unzip needed for composer, unless we document to use dev image for composer install
# unzip needed due to https://github.com/composer/composer/issues/4471
        unzip \
        git \
# packages useful for dev
        less \
        mariadb-client \
        vim \
        wget \
        tree \
        gdb-minimal \
    && rm -rf /var/lib/apt/lists/*

# Install and configure php plugins
RUN set -xe \
    && buildDeps=" \
        $PHP_EXTRA_BUILD_DEPS \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libxpm-dev \
        libpng-dev \
        libicu-dev \
        libxslt1-dev \
        libmemcached-dev \
        libzip-dev \
        libxml2-dev \
        libmagickwand-dev \
        libpq-dev \
    " \
	&& apt-get update -q -y && apt-get install -q -y --no-install-recommends $buildDeps && rm -rf /var/lib/apt/lists/* \
# Extract php source and install missing extensions
    && docker-php-source extract \
    && docker-php-ext-configure mysqli --with-mysqli=mysqlnd \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ --with-png-dir=/usr/include/ --with-xpm-dir=/usr/include/ --enable-gd-jis-conv \
    && docker-php-ext-install exif gd mbstring intl xsl zip mysqli pdo_mysql pdo_pgsql pgsql soap bcmath \
    && docker-php-ext-enable opcache \
    && cp /usr/src/php/php.ini-production ${PHP_INI_DIR}/php.ini \
    \
# Install imagemagick
    && for i in $(seq 1 3); do pecl install -o imagick && s=0 && break || s=$? && sleep 1; done; (exit $s) \
    && docker-php-ext-enable imagick \
# Install xdebug
    && for i in $(seq 1 3); do echo yes | pecl install -o "xdebug" && s=0 && break || s=$? && sleep 1; done; (exit $s) \
# Install blackfire: https://blackfire.io/docs/integrations/docker
    && version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;") \
    && curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s https://blackfire.io/api/v1/releases/probe/php/linux/amd64/$version \
    && tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp \
    && mv /tmp/blackfire-*.so $(php -r "echo ini_get('extension_dir');")/blackfire.so \
    && rm -f /tmp/blackfire-probe.tar.gz \
    \
# Install igbinary (for more efficient serialization in redis/memcached)
    && for i in $(seq 1 3); do pecl install -o igbinary && s=0 && break || s=$? && sleep 1; done; (exit $s) \
    && docker-php-ext-enable igbinary \
    \
# Install redis (manualy build in order to be able to enable igbinary)
    && for i in $(seq 1 3); do pecl install -o --nobuild redis && s=0 && break || s=$? && sleep 1; done; (exit $s) \
    && cd "$(pecl config-get temp_dir)/redis" \
    && phpize \
    && ./configure --enable-redis-igbinary \
    && make \
    && make install \
    && docker-php-ext-enable redis \
    && cd - \
    \
# Install memcached (manualy build in order to be able to enable igbinary)
    && for i in $(seq 1 3); do echo no | pecl install -o --nobuild memcached && s=0 && break || s=$? && sleep 1; done; (exit $s) \
    && cd "$(pecl config-get temp_dir)/memcached" \
    && phpize \
    && ./configure --enable-memcached-igbinary \
    && make \
    && make install \
    && docker-php-ext-enable memcached \
    && cd - \
    \
# Delete source & builds deps so it does not hang around in layers taking up space
    && pecl clear-cache \
    && rm -Rf "$(pecl config-get temp_dir)/*" \
    && docker-php-source delete \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $buildDeps

# Set timezone
RUN echo "UTC" > /etc/timezone && dpkg-reconfigure --frontend noninteractive tzdata

# Set pid file to be able to restart php-fpm
RUN sed -i "s@^\[global\]@\[global\]\n\npid = /run/php-fpm.pid@" ${PHP_INI_DIR}-fpm.conf

COPY conf.d/blackfire.ini ${PHP_INI_DIR}/conf.d/blackfire.ini
COPY conf.d/xdebug.ini ${PHP_INI_DIR}/conf.d/xdebug.ini.disabled

# Create Composer directory (cache and auth files) & Get Composer
RUN mkdir -p $COMPOSER_HOME \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# As application is put in as volume we do all needed operation on run
COPY scripts /scripts

# Add some custom config
COPY conf.d/php.ini ${PHP_INI_DIR}/conf.d/php.ini

RUN chmod 755 /scripts/*.sh

# Needed for docker-machine
RUN usermod -u 1000 www-data

WORKDIR /var/www

ENTRYPOINT ["/scripts/docker-entrypoint.sh"]

CMD php-fpm

EXPOSE 9000

{{ def target: .targets[.linux] -}}
FROM {{ target.image }}

# add our user and group first to make sure their IDs get assigned consistently, regardless of whatever dependencies get added
RUN set -eux; \
	groupadd --gid 999 --system mongodb; \
	useradd --uid 999 --system --gid mongodb --home-dir /data/db mongodb; \
	mkdir -p /data/db /data/configdb; \
	chown -R mongodb:mongodb /data/db /data/configdb

RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		ca-certificates \
		jq \
		numactl \
	; \
	if ! command -v ps > /dev/null; then \
		apt-get install -y --no-install-recommends procps; \
	fi; \
	rm -rf /var/lib/apt/lists/*

# grab gosu for easy step-down from root (https://github.com/tianon/gosu/releases)
ENV GOSU_VERSION 1.12
# grab "js-yaml" for parsing mongod's YAML config files (https://github.com/nodeca/js-yaml/releases)
ENV JSYAML_VERSION 3.13.1

RUN set -ex; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		wget \
	; \
	if ! command -v gpg > /dev/null; then \
		apt-get install -y --no-install-recommends gnupg dirmngr; \
		savedAptMark="$savedAptMark gnupg dirmngr"; \
	elif gpg --version | grep -q '^gpg (GnuPG) 1\.'; then \
# "This package provides support for HKPS keyservers." (GnuPG 1.x only)
		apt-get install -y --no-install-recommends gnupg-curl; \
	fi; \
	rm -rf /var/lib/apt/lists/*; \
	\
	dpkgArch="$(dpkg --print-architecture | awk -F- '{ print $NF }')"; \
	wget -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch"; \
	wget -O /usr/local/bin/gosu.asc "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$dpkgArch.asc"; \
	export GNUPGHOME="$(mktemp -d)"; \
	gpg --batch --keyserver hkps://keys.openpgp.org --recv-keys B42F6819007F00F88E364FD4036A9C25BF357DD4; \
	gpg --batch --verify /usr/local/bin/gosu.asc /usr/local/bin/gosu; \
	command -v gpgconf && gpgconf --kill all || :; \
	rm -r "$GNUPGHOME" /usr/local/bin/gosu.asc; \
	\
	wget -O /js-yaml.js "https://github.com/nodeca/js-yaml/raw/${JSYAML_VERSION}/dist/js-yaml.js"; \
# TODO some sort of download verification here
	\
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark > /dev/null; \
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	\
# smoke test
	chmod +x /usr/local/bin/gosu; \
	gosu --version; \
	gosu nobody true

RUN mkdir /docker-entrypoint-initdb.d

RUN set -ex; \
	export GNUPGHOME="$(mktemp -d)"; \
	set -- {{ .gpg | map(@sh) | join(" ") }}; \
	for key; do \
		gpg --batch --keyserver keyserver.ubuntu.com --recv-keys "$key"; \
	done; \
	mkdir -p /etc/apt/keyrings; \
	gpg --batch --export "$@" > /etc/apt/keyrings/mongodb.gpg; \
	command -v gpgconf && gpgconf --kill all || :; \
	rm -r "$GNUPGHOME"

# Allow build-time overrides (eg. to build image with MongoDB Enterprise version)
# Options for MONGO_PACKAGE: mongodb-org OR mongodb-enterprise
# Options for MONGO_REPO: repo.mongodb.org OR repo.mongodb.com
# Example: docker build --build-arg MONGO_PACKAGE=mongodb-enterprise --build-arg MONGO_REPO=repo.mongodb.com .
ARG MONGO_PACKAGE=mongodb-org{{ if (env.version != env.rcVersion) and (env.rcVersion | split(".")[1] | tonumber % 2 == 1) then "-unstable" else "" end }}
ARG MONGO_REPO=repo.mongodb.org
ENV MONGO_PACKAGE=${MONGO_PACKAGE} MONGO_REPO=${MONGO_REPO}

ENV MONGO_MAJOR {{ if env.version != env.rcVersion then "testing" else env.version end }}
RUN echo "deb [ signed-by=/etc/apt/keyrings/mongodb.gpg ] http://$MONGO_REPO/apt/{{ target.image | gsub(":.*$"; "") }} {{ target.suite }}/${MONGO_PACKAGE%-unstable}/$MONGO_MAJOR {{ if target.image | test("^debian") then "main" else "multiverse" end }}" | tee "/etc/apt/sources.list.d/${MONGO_PACKAGE%-unstable}.list"

{{ if .notes then ( -}}
# {{ .notes }}
{{ ) else "" end -}}
ENV MONGO_VERSION {{ .version | gsub("-"; "~") }}
{{ if .date or .githash then ( -}}
# {{ [ .date // empty, "https://github.com/mongodb/mongo/tree/" + .githash // empty ] | join(", ") }}
{{ ) else "" end -}}

RUN set -x \
# installing "mongodb-enterprise" pulls in "tzdata" which prompts for input
	&& export DEBIAN_FRONTEND=noninteractive \
	&& apt-get update \
	&& apt-get install -y \
		${MONGO_PACKAGE}=$MONGO_VERSION \
		${MONGO_PACKAGE}-server=$MONGO_VERSION \
		${MONGO_PACKAGE}-shell=$MONGO_VERSION \
		${MONGO_PACKAGE}-mongos=$MONGO_VERSION \
		${MONGO_PACKAGE}-tools=$MONGO_VERSION \
	&& rm -rf /var/lib/apt/lists/* \
	&& rm -rf /var/lib/mongodb \
	&& mv /etc/mongod.conf /etc/mongod.conf.orig

VOLUME /data/db /data/configdb

# ensure that if running as custom user that "mongosh" has a valid "HOME"
# https://github.com/docker-library/mongo/issues/524
ENV HOME /data/db

COPY docker-entrypoint.sh /usr/local/bin/
ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 27017
CMD ["mongod"]