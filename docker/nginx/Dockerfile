FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --chown=nginx:nginx public /var/www/html/public
COPY --chown=nginx:nginx --from=frontend /app/public/build /var/www/html/public/build
RUN find /var/www/html/public -type d -exec chmod 0755 {} + \
    && find /var/www/html/public -type f -exec chmod 0644 {} +
