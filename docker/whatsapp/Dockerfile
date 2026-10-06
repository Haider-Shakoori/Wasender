FROM node:22-bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        chromium \
        ca-certificates \
        dumb-init \
        gosu \
        fonts-liberation \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

ENV PUPPETEER_CACHE_DIR=/opt/puppeteer

COPY whatsapp-service/package.json whatsapp-service/package-lock.json ./
RUN npm ci

COPY whatsapp-service ./
RUN npm run typecheck \
    && npm test \
    && npm run build \
    && npm prune --omit=dev

COPY docker/whatsapp/entrypoint.sh /usr/local/bin/wasender-whatsapp-entrypoint
RUN chmod +x /usr/local/bin/wasender-whatsapp-entrypoint

ENV NODE_ENV=production
ENV PUPPETEER_CACHE_DIR=/opt/puppeteer
ENV HOME=/var/lib/wasender

ENTRYPOINT ["/usr/bin/dumb-init", "--", "/usr/local/bin/wasender-whatsapp-entrypoint"]
CMD ["node", "dist/server.js"]
