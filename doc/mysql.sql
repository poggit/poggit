CREATE TABLE installs (
    uid INT PRIMARY KEY,
    name VARCHAR(256) UNIQUE,
    installId INT UNIQUE,
    type TINYINT -- User = 1, Organization = 2
);

CREATE TABLE users (
    uid INT PRIMARY KEY,
    name VARCHAR(256) UNIQUE,
    token VARCHAR(64),
    opts VARCHAR(16384) DEFAULT '{}'
);

CREATE TABLE repos (
    repoId INT PRIMARY KEY,
    owner VARCHAR(256),
    name VARCHAR(256),
    build TINYINT,
    rel TINYINT,
    accessWith VARCHAR(64)
);
CREATE INDEX full_name ON repos (owner, name);
