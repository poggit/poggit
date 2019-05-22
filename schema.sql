CREATE TABLE user (
    id           BIGINT                                       NOT NULL PRIMARY KEY,
    name         VARCHAR(255)                                 NOT NULL UNIQUE,
    email        VARCHAR(255)                                 NULL,
    is_org       BOOL                                         NOT NULL,
    first_login  TIMESTAMP                                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login   TIMESTAMP                                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registered   BOOL                                         NOT NULL,
    install_id   BIGINT                                       NULL UNIQUE,
    access_level ENUM ('banned', 'user', 'reviewer', 'admin') NOT NULL DEFAULT 'user'
);

CREATE TABLE user_ip (
    user  BIGINT       NOT NULL,
    ip    VARCHAR(255) NOT NULL,
    first TIMESTAMP    NOT NULL,
    last  TIMESTAMP    NOT NULL,
    PRIMARY KEY (user, ip),
    FOREIGN KEY (user) REFERENCES user(id) ON DELETE CASCADE
);

CREATE TABLE repo (
    id      BIGINT       NOT NULL PRIMARY KEY,
    owner   BIGINT       NOT NULL,
    name    VARCHAR(255) NOT NULL,
    private BOOL         NOT NULL DEFAULT FALSE,
    fork    BOOL         NOT NULL DEFAULT FALSE,
    FOREIGN KEY (owner) REFERENCES user(id) ON DELETE CASCADE
);

CREATE TABLE resource (
    id          INT          NOT NULL PRIMARY KEY,
    mime        VARCHAR(255) NOT NULL,
    date        TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
    expiry      TIMESTAMP    NULL DEFAULT NULL KEY,
    access_repo INT          NULL DEFAULT NULL,
    downloads   INT               DEFAULT 0,
    class       VARCHAR(255) NOT NULL KEY,
    size        INT          NOT NULL,
    FOREIGN KEY (access_repo) REFERENCES repo(id) ON DELETE CASCADE
);
CREATE TABLE resource_blob (
    id      INT NOT NULL PRIMARY KEY,
    content LONGBLOB,
    FOREIGN KEY (id) REFERENCES resource(id) ON DELETE CASCADE
);

CREATE TABLE project (
    id    INT          NOT NULL PRIMARY KEY AUTO_INCREMENT,
    owner BIGINT       NOT NULL,
    repo  BIGINT       NOT NULL,
    name  VARCHAR(255) NOT NULL,
    FOREIGN KEY (owner) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY (repo) REFERENCES repo(id) ON DELETE CASCADE
);

CREATE TABLE build (
    id           INT                 NOT NULL PRIMARY KEY AUTO_INCREMENT,
    phar         INT                 NULL UNIQUE,
    project      INT                 NOT NULL KEY,
    cause        ENUM ('push', 'pr') NOT NULL,
    pr_number    INT                 NULL KEY,
    pr_head_repo INT                 NOT NULL KEY,
    branch       VARCHAR(255)        NOT NULL,
    sha          CHAR(40)            NOT NULL,
    number       INT                 NOT NULL,
    date         TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cause_user   INT                 NOT NULL KEY,
    path         VARCHAR(255)        NOT NULL,
    log          INT                 NOT NULL,
    KEY (cause, number),
    FOREIGN KEY (phar) REFERENCES resource(id) ON DELETE SET NULL,
    FOREIGN KEY (log) REFERENCES resource(id) ON DELETE SET NULL
);

