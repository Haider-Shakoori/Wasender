import { createApp } from './app.js';
import { CallbackClient } from './callback-client.js';
import { loadConfig } from './config.js';
import { SessionManager } from './session-manager.js';
import { CampaignService } from './campaign-service.js';

const config = loadConfig();
const sessions = new SessionManager(config, new CallbackClient(config.callbackUrl, config.hmacSecret, config.callbackTimeoutMs));
const callbacks = new CallbackClient(config.callbackUrl, config.hmacSecret, config.callbackTimeoutMs);
const campaigns = new CampaignService(config, sessions, callbacks);
let draining = false;
const server = createApp(config, sessions, campaigns, () => draining).listen(config.port, () => console.log(`WhatsApp connector listening on ${config.port}`));
const heartbeat = setInterval(() => void sessions.heartbeat(), 60_000);
heartbeat.unref();

async function shutdown(): Promise<void> {
  if (draining) return;
  draining = true;
  clearInterval(heartbeat);
  campaigns.stop();
  await Promise.race([new Promise<void>((resolve) => server.close(() => resolve())), new Promise<void>((resolve) => setTimeout(resolve, 10_000))]);
  await sessions.destroyAll();
}
process.once('SIGTERM', () => void shutdown().finally(() => process.exit(0)));
process.once('SIGINT', () => void shutdown().finally(() => process.exit(0)));
