ALTER TABLE users
    ADD COLUMN lastLogin TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    AFTER token;
ALTER TABLE users
    ADD COLUMN lastNotif TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    AFTER lastLogin;
DROP TABLE useronline;
CREATE TABLE users_online (
    ip     VARCHAR(40) PRIMARY KEY,
    lastOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
DELIMITER $$
CREATE FUNCTION KeepOnline(p_ip VARCHAR(40), p_uid INT UNSIGNED)
    RETURNS INT
    BEGIN
        DECLARE cnt INT;

        IF p_uid != 0
        THEN
            UPDATE users
            SET lastLogin = CURRENT_TIMESTAMP
            WHERE uid = p_uid;
        END IF;

        INSERT INTO users_online (ip, lastOn) VALUES (p_ip, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE lastOn = CURRENT_TIMESTAMP;

        DELETE FROM users_online
        WHERE UNIX_TIMESTAMP() - UNIX_TIMESTAMP(lastOn) < 300;

        SELECT COUNT(*)
        INTO cnt
        FROM users_online;

        RETURN cnt;
    END$$
DELIMITER ;
