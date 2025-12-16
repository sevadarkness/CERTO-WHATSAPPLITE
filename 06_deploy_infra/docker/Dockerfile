# Dockerfile - Rede Alabama (Painel PHP + módulos)
FROM php:8.2-apache

# Dependências do sistema + extensões PHP necessárias no runtime
# - intl (ICU)
# - mbstring (oniguruma)
# - zip (libzip)
# - curl (libcurl)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libcurl4-openssl-dev \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        mbstring \
        zip \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Apache modules comuns
RUN a2enmod rewrite headers

# Diretório da aplicação dentro do container
WORKDIR /var/www/html

# Copia o código do projeto (build context deve ser a raiz do projeto)
COPY . /var/www/html

# VHost do Apache:
# - DocumentRoot aponta para 01_backend_painel_php (necessário por rotas absolutas /modules/...)
# - expõe módulos opcionais via Alias (/marketing, /ai)
COPY 06_deploy_infra/docker/apache-site.conf /etc/apache2/sites-available/000-default.conf

# Permissões básicas (ajuste conforme sua política)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Health check para verificar se o servidor está respondendo
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/healthz.php || exit 1

CMD ["apache2-foreground"]
