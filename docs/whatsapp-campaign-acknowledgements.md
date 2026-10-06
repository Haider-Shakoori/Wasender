# Campaign acknowledgements

The session's canonical `message_ack` listener correlates by serialized WhatsApp message ID, never phone. State is monotonic:
sent → delivered → read; read implies delivered and cannot be downgraded. Node's persistent outbox retries authenticated
callbacks with stable IDs and bounded exponential backoff. Laravel locks correlated rows, processes its event inbox
transactionally, and accepts late acknowledgements without reopening terminal executions or consuming usage again.
