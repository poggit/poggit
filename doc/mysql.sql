DROP TABLE IF EXISTS users;
CREATE TABLE users (
    uid INT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) UNIQUE,
    token VARCHAR(64),
    opts VARCHAR(16383) DEFAULT '{}'
);
DROP TABLE IF EXISTS repos;
CREATE TABLE repos (
    repoId INT UNSIGNED PRIMARY KEY,
    owner VARCHAR(256),
    name VARCHAR(256),
    private BIT(1),
    build BIT(1) DEFAULT 0,
    rel BIT(1) DEFAULT 0,
    accessWith INT UNSIGNED REFERENCES users(uid),
    webhookId BIGINT UNSIGNED
);
CREATE INDEX full_name ON repos (owner, name);
DROP TABLE IF EXISTS projects;
CREATE TABLE projects (
    projectId INT UNSIGNED PRIMARY KEY,
    repoId INT UNSIGNED REFERENCES repos(repoId),
    name VARCHAR(255),
    path VARCHAR(1000),
    type TINYINT UNSIGNED, -- Plugin = 0, Library = 1
    framework VARCHAR(100), -- default, nowhere
    lang BIT(1)
);
CREATE UNIQUE INDEX repo_proj ON projects (repoId, name);
DROP TABLE IF EXISTS resources;
CREATE TABLE resources (
    resourceId BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100), -- phar, md, png, zip, etc.
    mimeType VARCHAR(100),
    created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    accessFilters VARCHAR(16383) DEFAULT '[]',
    duration INT UNSIGNED
);
DROP TABLE IF EXISTS builds;
CREATE TABLE builds (
    buildId BIGINT UNSIGNED PRIMARY KEY,
    resourceId BIGINT UNSIGNED REFERENCES resources(resourceId),
    projectId INT REFERENCES projects(projectId),
    class TINYINT, -- Dev = 1, Beta = 2, Release = 3
    branch VARCHAR(255) DEFAULT 'master',
    head CHAR(40),
    internal INT, -- internal (project,class) build number, as opposed to global build number
    created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3)
);
CREATE INDEX builds_by_project ON builds (projectId);
DROP TABLE IF EXISTS releases;
CREATE TABLE releases (
    releaseId INT UNSIGNED PRIMARY KEY,
    artifact BIGINT UNSIGNED REFERENCES resources(resourceId),
    projectId INT UNSIGNED REFERENCES projects(projectId),
    version VARCHAR(100), -- user-defined version ID, may duplicate, from
    type TINYINT UNSIGNED, -- Release = 1, Pre-release = 2
    spoon VARCHAR(100),
    spoonVersion VARCHAR(100),
    description BIGINT UNSIGNED REFERENCES resources(resourceId),
    license VARCHAR(100), -- name of license, or 'file'
    licenseRes BIGINT DEFAULT -1 -- resourceId of license, only set if `license` is set to 'file'
);
CREATE INDEX releases_by_project ON releases (projectId);
DROP TABLE IF EXISTS release_meta;
CREATE TABLE release_meta (
    releaseId INT UNSIGNED REFERENCES releases(releaseId),
    type TINYINT UNSIGNED, -- Category = 1, Permission = 2, Requirement = 3
    val VARCHAR(255)
);
CREATE INDEX release_meta_index ON release_meta (releaseId, type);
