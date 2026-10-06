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

# whatsapp-web.js 1.34.6 can lose its Puppeteer execution context while
# WhatsApp performs a SPA navigation during authentication/session restore.
# Pin the upstream Client.js fix from commit 1780711 for deterministic builds.
COPY docker/whatsapp/patches/whatsapp-web-client-1780711.js /app/node_modules/whatsapp-web.js/src/Client.js
COPY docker/whatsapp/patches/WHATSAPP_WEB_JS_LICENSE.txt /usr/share/doc/wasender/WHATSAPP_WEB_JS_LICENSE.txt
RUN chmod 0644 /app/node_modules/whatsapp-web.js/src/Client.js /usr/share/doc/wasender/WHATSAPP_WEB_JS_LICENSE.txt

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
