FROM node:22-alpine AS frontend
WORKDIR /build
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ .
# Page copy shared with the API (imported as @content, see vite.config.ts)
COPY api/resources/content /api/resources/content
RUN npm run build

FROM caddy:2-alpine
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=frontend /build/dist /srv/frontend
# php_fastcgi checks that index.php exists on Caddy's side; PHP itself runs in the app container.
COPY api/public /var/www/api/public
