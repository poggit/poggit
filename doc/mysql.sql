CREATE TABLE installs (
    uid INT PRIMARY KEY,
    name VARCHAR(255) UNIQUE,
    installId INT UNIQUE,
    type TINYINT -- User = 1, Organization = 2
);

CREATE TABLE users (
    uid INT PRIMARY KEY,
    name VARCHAR(255) UNIQUE,
    token VARCHAR(64),
    opts VARCHAR(16383) DEFAULT '{}'
);
CREATE TABLE repos (
    repoId INT PRIMARY KEY,
    owner VARCHAR(256),
    name VARCHAR(256),
    private TINYINT,
    build TINYINT DEFAULT 0,
    rel TINYINT DEFAULT 0,
    accessWith VARCHAR(64)
);
CREATE INDEX full_name ON repos (owner, name);
CREATE TABLE projects (
    projectId INT PRIMARY KEY,
    repoId INT,
    name VARCHAR(255),
    type TINYINT, -- Plugin = 1, Library = 2
    framework VARCHAR(100), -- default, nowhere
    lang TINYINT
);
CREATE UNIQUE INDEX repo_proj ON projects (repoId, name);
CREATE TABLE builds (
    buildId BIGINT PRIMARY KEY,
    resId BIGINT UNIQUE,
    projectId INT,
    class TINYINT, -- Dev = 1, Beta = 2, Release = 3
    time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX builds_by_project ON builds (projectId);
CREATE TABLE releases (
    releaseId INT PRIMARY KEY,
    artifact BIGINT, -- resId
    projectId INT,
    version VARCHAR(100), -- project internal version ID, may duplicate, from
    type TINYINT, -- Release = 1, Pre-release = 2
    spoon VARCHAR(100),
    spoonVersion VARCHAR(100),
    description BIGINT, -- resId
    license VARCHAR(100), -- name of license, or 'file'
    licenseRes BIGINT DEFAULT -1 -- resId of license, only set if `license` is set to 'file'
);
CREATE INDEX releases_by_project ON releases (projectId);
CREATE TABLE release_meta (
    releaseId INT,
    type VARCHAR(100), -- cat, perm, require
    val VARCHAR(255)
);
CREATE INDEX release_meta_index ON release_meta (releaseId, type);
CREATE TABLE resources (
    resId BIGINT PRIMARY KEY,
    type VARCHAR(100), -- phar, md, png, zip, etc.
    time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
