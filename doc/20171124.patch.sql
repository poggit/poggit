ALTER TABLE rsr_dl_ips ADD COLUMN latest TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE rsr_dl_ips ADD COLUMN count INT DEFAULT 1;

DROP FUNCTION IncRsrDlCnt;
DELIMITER $$
CREATE FUNCTION IncRsrDlCnt(p_resourceId BIGINT UNSIGNED, p_ip VARCHAR(56))
    RETURNS INT
    BEGIN
        DECLARE v_count INT;

        SELECT IFNULL((SELECT count
                       FROM rsr_dl_ips
                       WHERE resourceId = p_resourceId AND ip = p_ip),
                      0)
        INTO v_count;

        IF v_count > 0
        THEN
            UPDATE rsr_dl_ips
            SET latest = CURRENT_TIMESTAMP, count = v_count + 1
            WHERE resourceId = p_resourceId AND ip = p_ip;
        ELSE
            UPDATE resources
            SET dlCount = dlCount + 1
            WHERE resourceId = p_resourceId;
            INSERT INTO rsr_dl_ips (resourceId, ip) VALUES (p_resourceId, p_ip);
        END IF;

        RETURN v_count + 1;
    END $$
