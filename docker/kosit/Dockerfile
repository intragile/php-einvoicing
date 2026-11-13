# The KoSIT validator (Java) running in daemon mode over the XRechnung validator configuration,
# used as the conformance oracle of the XRechnung validation: docker/kosit/run.sh.
#
#   docker build --tag php-einvoicing-kosit:1.6.3 docker/kosit
#
# The base image is pinned by exact tag and manifest-list digest (eclipse-temurin:21-jre resolved
# to 21.0.13_9-jre on 2026-11-13). The validator jar and the configuration release zip are
# downloaded from their release URLs and checked against the SHA-256 values that
# IntrAgile\EInvoicing\Validation\XRechnung\XRechnungSources pins; a unit test keeps the two in
# sync. Nothing from this image is ever committed.

ARG BASE_IMAGE=eclipse-temurin:21.0.13_9-jre@sha256:18bb4a91a3dea3f03ee31d3755af34dfb40f904fc77ab56cdc2f092e32fff9c2

# hadolint ignore=DL3006
FROM ${BASE_IMAGE} AS fetch

ARG KOSIT_VALIDATOR_URL=https://github.com/itplr-kosit/validator/releases/download/v1.6.3/validator-1.6.3-standalone.jar
ARG KOSIT_VALIDATOR_SHA256=799e64befca97d4080e03608c80b85dd5a5ecc5f4ae4f35d1116ec2855b9a7c9
ARG KOSIT_CONFIGURATION_URL=https://github.com/itplr-kosit/validator-configuration-xrechnung/releases/download/v2026-08-31/xrechnung-3.0.2-validator-configuration-2026-08-31.zip
ARG KOSIT_CONFIGURATION_SHA256=2530cd107c414511c5d0462ec10f886910395abfca820db82e83d70bf01221a8

# hadolint ignore=DL3008
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*; \
    mkdir -p /opt/kosit/configuration; \
    curl -fsSL --retry 3 --connect-timeout 30 --max-time 600 -o /opt/kosit/validator.jar "$KOSIT_VALIDATOR_URL"; \
    echo "${KOSIT_VALIDATOR_SHA256}  /opt/kosit/validator.jar" | sha256sum -c -; \
    curl -fsSL --retry 3 --connect-timeout 30 --max-time 600 -o /tmp/configuration.zip "$KOSIT_CONFIGURATION_URL"; \
    echo "${KOSIT_CONFIGURATION_SHA256}  /tmp/configuration.zip" | sha256sum -c -; \
    unzip -q /tmp/configuration.zip -d /opt/kosit/configuration; \
    rm /tmp/configuration.zip; \
    test -f /opt/kosit/configuration/scenarios.xml

# hadolint ignore=DL3006
FROM ${BASE_IMAGE}

RUN set -eux; \
    groupadd --system --gid 10001 kosit; \
    useradd --system --uid 10001 --gid kosit --no-create-home --shell /usr/sbin/nologin kosit

COPY --from=fetch --chown=root:root /opt/kosit /opt/kosit

USER kosit
WORKDIR /opt/kosit/configuration
EXPOSE 8080

# Daemon mode (flags from `--help` of validator 1.6.3): -s scenarios, -r repository of the
# scenario resources, -D daemon, -G no web GUI, -H bind address, -P port. A document is validated
# by POSTing it to / and the response body is the KoSIT report (namespace
# http://www.xoev.de/de/validator/varl/1).
ENTRYPOINT ["java", "-jar", "/opt/kosit/validator.jar", "-s", "/opt/kosit/configuration/scenarios.xml", "-r", "/opt/kosit/configuration", "-D", "-G", "-H", "0.0.0.0", "-P", "8080"]
