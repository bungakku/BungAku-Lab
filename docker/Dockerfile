# BungAku-Lab in a container: PHP + Node + Playwright with its browsers. The database is a separate service (see docker-compose.yml).
# The base image tag must match PLAYWRIGHT_VERSION in versions.env (the image ships the matching browser builds).
ARG PLAYWRIGHT_VERSION=1.63.0
FROM mcr.microsoft.com/playwright:v${PLAYWRIGHT_VERSION}-noble

ENV DEBIAN_FRONTEND=noninteractive
# Ubuntu 24.04 (noble) ships PHP 8.3, the version WordPress recommends.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      php-cli php-mysql php-mbstring php-xml php-curl php-zip php-gd php-intl \
      mariadb-client git unzip zip curl ca-certificates diffutils \
 && rm -rf /var/lib/apt/lists/*

ENV LAB_HOME=/lab-home \
    DB_MODE=external \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

WORKDIR /lab
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["status"]
