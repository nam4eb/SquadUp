FROM serversideup/php:8.3-cli-alpine

USER root
RUN install-php-extensions gd intl sockets
USER www-data

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
