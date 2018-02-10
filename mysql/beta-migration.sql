CREATE TABLE webhook_executions (
	deliveryId CHAR(36),
	logRsr     BIGINT UNSIGNED,
	complete   BIT(1) DEFAULT 0,
	FOREIGN KEY (logRsr) REFERENCES resources (resourceId)
);

UPDATE repos SET build = 0;
ALTER TABLE repos DROP COLUMN accessWith;
ALTER TABLE repos DROP COLUMN webhookId;
ALTER TABLE repos DROP COLUMN webhookKey;
ALTER TABLE repos ADD COLUMN installation INT;
