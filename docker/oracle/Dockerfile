# PHP CLI with the SaxonC-HE PHP extension, used to run the official CEN EN 16931
# validation stylesheets (XSLT 2.0) as a test oracle: vendor/bin/phpunit --group oracle.
#
#   docker build --build-arg PHP_VERSION=8.4 -t php-einvoicing-oracle:php8.4 docker/oracle
#
# The SaxonC-HE download is pinned by SHA-256 per architecture. Build dependencies are
# installed and purged in the same layer to keep the image small.

ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli-trixie

ARG TARGETARCH
ARG SAXONC_VERSION=13-1-1
ARG SAXONC_SHA256_AMD64=66bdeba467fcfa0be1f78c7ae477524773a5e39821a159884a7fd1aab6301d16
ARG SAXONC_SHA256_ARM64=c040be979e903472edc307d248d95cd23176f664723f4b8a7f9b02d8c598acdf

RUN set -eux; \
    arch="${TARGETARCH:-$(dpkg --print-architecture)}"; \
    case "$arch" in \
        amd64) zip_arch=x86_64; sha256="$SAXONC_SHA256_AMD64" ;; \
        arm64) zip_arch=arm64; sha256="$SAXONC_SHA256_ARM64" ;; \
        *) echo "Unsupported architecture: $arch" >&2; exit 1 ;; \
    esac; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip $PHPIZE_DEPS; \
    curl -fsSL --retry 3 --connect-timeout 30 --max-time 600 -o /tmp/saxonc.zip \
        "https://downloads.saxonica.com/SaxonC/HE/13/SaxonCHE-linux-${zip_arch}-${SAXONC_VERSION}.zip"; \
    echo "${sha256}  /tmp/saxonc.zip" | sha256sum -c -; \
    mkdir /tmp/saxonc; \
    unzip -q /tmp/saxonc.zip -d /tmp/saxonc; \
    src="$(find /tmp/saxonc -mindepth 1 -maxdepth 1 -type d -name 'SaxonCHE-*')"; \
    cp -a "$src"/SaxonCHE/lib/libsaxonc-he.so* "$src"/SaxonCCoreHE/lib/libsaxonc-core-he.so* /usr/local/lib/; \
    mkdir -p /usr/local/share/doc/saxonc-he; \
    cp -a "$src"/notices/. /usr/local/share/doc/saxonc-he/; \
    ldconfig; \
    cd "$src/php/src"; \
    phpize; \
    ./configure --with-saxonc="$src/SaxonCHE" --with-saxonc-core="$src/SaxonCCoreHE"; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable saxon; \
    cd /; \
    rm -rf /tmp/saxonc /tmp/saxonc.zip; \
    apt-mark auto '.*' > /dev/null; \
    [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*; \
    php -m | grep -qx saxonc; \
    php -r 'echo (new Saxon\SaxonProcessor)->version(), PHP_EOL;'

WORKDIR /app
