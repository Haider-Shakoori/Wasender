# Campaign usage consumption

Launch reserves capacity. One unit is consumed only after a stable WhatsApp message ID crosses the sent boundary. A locked
reservation plus recipient `usage_consumed_at` marker makes this idempotent. Retries, duplicate callbacks, delivery/read
events, and unknown outcomes consume nothing extra. Confirmed unsent permanent failures release their unused reservation.
