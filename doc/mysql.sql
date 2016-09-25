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
    private BIT(1),
    build BIT(1) DEFAULT 0,
    rel BIT(1) DEFAULT 0,
    accessWith VARCHAR(64),
    webhookId BIGINT
);
CREATE INDEX full_name ON repos (owner, name);
CREATE TABLE projects (
    projectId INT PRIMARY KEY,
    repoId INT REFERENCES repos(repoId),
    name VARCHAR(255),
    type TINYINT, -- Plugin = 1, Library = 2
    framework VARCHAR(100), -- default, nowhere
    lang BIT(1)
);
CREATE UNIQUE INDEX repo_proj ON projects (repoId, name);
CREATE TABLE resources (
    resId BIGINT PRIMARY KEY,
    type VARCHAR(100), -- phar, md, png, zip, etc.
    time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE builds (
    buildId BIGINT PRIMARY KEY,
    resId BIGINT UNIQUE,
    projectId INT REFERENCES projects(projectId),
    class TINYINT, -- Dev = 1, Beta = 2, Release = 3
    internal INT, -- internal (project,class) build number, as opposed to global build number
    time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX builds_by_project ON builds (projectId);
CREATE TABLE releases (
    releaseId INT PRIMARY KEY,
    artifact BIGINT REFERENCES resources(resId),
    projectId INT REFERENCES projects(projectId),
    version VARCHAR(100), -- user-defined version ID, may duplicate, from
    type TINYINT, -- Release = 1, Pre-release = 2
    spoon VARCHAR(100),
    spoonVersion VARCHAR(100),
    description BIGINT REFERENCES resources(resId),
    license VARCHAR(100), -- name of license, or 'file'
    licenseRes BIGINT DEFAULT -1 -- resId of license, only set if `license` is set to 'file'
);
CREATE INDEX releases_by_project ON releases (projectId);
CREATE TABLE release_meta (
    releaseId INT REFERENCES releases(releaseId),
    type TINYINT, -- Category = 1, Permission = 2, Requirement = 3
    val VARCHAR(255)
);
CREATE INDEX release_meta_index ON release_meta (releaseId, type);
