# Rule-based WhatsApp chatbots

Tenant chatbots are explicitly assigned to one ready WhatsApp session and must be published and enabled before processing stored inbound text messages. Rules are deterministic and ordered by ascending priority. Supported matches are any text, exact text, contains, starts with, and bounded contact conditions. Supported actions are plain-text reply, frozen published-template reply, human handoff, and stop bot; unmatched messages may use one non-recursive fallback.

Processing starts only after the Shared Inbox stores the inbound message. A unique chatbot/inbound-message execution, canonical outbound idempotency key, direct-conversation checks, cooldown, and per-minute cap prevent duplicate replies and loops. Replies use normal transactional messaging and its subscription usage accounting—Laravel never calls Node directly. Handoff and stopped states block future replies until an authorized inbox user resumes the bot; safe execution history stores no message body or private contact payload.

The tenant UI provides list filters, create/edit rule cards, publish/enable/archive lifecycle, and bounded execution history. Platform visibility is read-only. Regex, NLP, AI/LLMs, media understanding, bot chaining, and automated tests are deferred until their respective future scope and final project QA.
