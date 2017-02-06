DROP TABLE IF EXISTS users;
CREATE TABLE users (
    uid INT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) UNIQUE,
    token VARCHAR(64),
    opts VARCHAR(16383) DEFAULT '{}'
);
DROP TABLE IF EXISTS user_ips;
CREATE TABLE user_ips (
    uid INT UNSIGNED REFERENCES users(uid),
    ip VARCHAR(100),
    time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(uid, ip)
);
DROP TABLE IF EXISTS repos;
CREATE TABLE repos (
    repoId INT UNSIGNED PRIMARY KEY,
    owner VARCHAR(256),
    name VARCHAR(256),
    private BIT(1),
    build BIT(1) DEFAULT 0,
    accessWith INT UNSIGNED REFERENCES users(uid),
    webhookId BIGINT UNSIGNED,
    webhookKey BINARY(8),
    KEY full_name (owner, name)
);
DROP TABLE IF EXISTS projects;
CREATE TABLE projects (
    projectId INT UNSIGNED PRIMARY KEY,
    repoId INT UNSIGNED REFERENCES repos(repoId),
    name VARCHAR(255),
    path VARCHAR(1000),
    type TINYINT UNSIGNED, -- Plugin = 0, Library = 1
    framework VARCHAR(100), -- default, nowhere
    lang BIT(1),
    UNIQUE KEY repo_proj (repoId, name)
);
DROP TABLE IF EXISTS project_subs;
CREATE TABLE project_subs (
    projectId INT UNSIGNED REFERENCES projects(projectId),
    userId INT UNSIGNED REFERENCES users(uid),
    level TINYINT DEFAULT 1 -- New Build = 1
);
DROP TABLE IF EXISTS resources;
CREATE TABLE resources (
    resourceId BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100), -- phar, md, png, zip, etc.
    mimeType VARCHAR(100),
    created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    accessFilters VARCHAR(8191) DEFAULT '[]',
    dlCount BIGINT DEFAULT 0,
    duration INT UNSIGNED,
    relMd BIGINT UNSIGNED DEFAULT NULL REFERENCES resources(resourceId)
) AUTO_INCREMENT=2;
INSERT INTO resources (resourceId, type, mimeType, accessFilters, dlCount, duration) VALUES
    (1, '', 'text/plain', '[]', 0, 315360000);
DROP TABLE IF EXISTS builds;
CREATE TABLE builds (
    buildId BIGINT UNSIGNED PRIMARY KEY,
    resourceId BIGINT UNSIGNED REFERENCES resources(resourceId),
    projectId INT REFERENCES projects(projectId),
    class TINYINT, -- Dev = 1, Beta = 2, Release = 3
    branch VARCHAR(255) DEFAULT 'master',
    sha CHAR(40),
    cause VARCHAR(8191),
    internal INT, -- internal (project,class) build number, as opposed to global build number
    created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    triggerUser INT UNSIGNED DEFAULT 0, -- not necessarily REFERENCES users(uid), because may not have registered on Poggit yet
    KEY builds_by_project (projectId)
);
DROP TABLE IF EXISTS builds_statuses;
CREATE TABLE builds_statuses (
    buildId BIGINT UNSIGNED REFERENCES builds(buildId),
    level TINYINT NOT NULL,
    class VARCHAR(255) NOT NULL,
    body VARCHAR(8101) DEFAULT '{}' NOT NULL,
    KEY statuses_by_build(buildId),
    KEY statuses_by_level(buildId, level)
);
DROP TABLE IF EXISTS releases;
CREATE TABLE releases (
    releaseId INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    shortDesc VARCHAR(255) DEFAULT '',
    artifact BIGINT UNSIGNED REFERENCES resources(resourceId),
    projectId INT UNSIGNED REFERENCES projects(projectId),
    buildId INT UNSIGNED REFERENCES builds(buildId),
    version VARCHAR(100), -- user-defined version ID, may duplicate
    description BIGINT UNSIGNED REFERENCES resources(resourceId),
    icon VARCHAR(511) DEFAULT NULL, -- url to GitHub raw
    changelog BIGINT UNSIGNED REFERENCES resources(resourceId),
    license VARCHAR(100), -- name of license, or 'file'
    licenseRes BIGINT DEFAULT 1, -- resourceId of license, only set if `license` is set to 'file'
    flags SMALLINT DEFAULT 0, -- for example, featured
    creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    state TINYINT DEFAULT 0,
    KEY releases_by_project (projectId),
    KEY releases_by_name (name)
);
DROP TABLE IF EXISTS release_categories;
CREATE TABLE release_categories (
    projectId INT UNSIGNED REFERENCES projects(projectId),
    category SMALLINT UNSIGNED NOT NULL,
    isMainCategory BIT(1)
);
DROP TABLE IF EXISTS release_keywords;
CREATE TABLE release_keywords (
    projectId INT UNSIGNED REFERENCES projects(projectId),
    word VARCHAR(100) NOT NULL
);
DROP TABLE IF EXISTS release_spoons;
CREATE TABLE release_spoons (
    releaseId INT UNSIGNED REFERENCES releases(releaseId),
    since VARCHAR(16),
    till VARCHAR(16)
);
DROP TABLE IF EXISTS release_deps;
CREATE TABLE release_deps (
    releaseId INT UNSIGNED REFERENcES releases(releaseId),
    name VARCHAR(100) NOT NULL,
    version VARCHAR(100) NOT NULL,
    depRelId INT UNSIGNED DEFAULT NULL,
    isHard BIT(1)
);
DROP TABLE IF EXISTS release_reqr;
CREATE TABLE release_reqr (
    releaseId INT UNSIGNED REFERENCES releases(releaseId),
    type TINYINT,
    details VARCHAR(255) DEFAULT '',
    isRequire BIT(1)
);
CREATE TABLE `release_perms` (
  `releaseId` int(10) unsigned DEFAULT NULL,
  `type` tinyint(3) unsigned DEFAULT NULL,
  `val` tinyint(3) DEFAULT NULL,
  KEY `release_meta_index` (`releaseId`,`type`)
);
DROP TABLE IF EXISTS release_reviews;
CREATE TABLE release_reviews (
    releaseId INT UNSIGNED REFERENCES releases(releaseId),
    user INT UNSIGNED REFERENCES users(uid),
    criteria INT UNSIGNED,
    type TINYINT UNSIGNED, -- Official = 1, User = 2, Robot = 3
    cat TINYINT UNSIGNED, -- perspective: code? test?
    score SMALLINT UNSIGNED,
    message VARCHAR(8191) DEFAULT '',
    created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY reviews_by_plugin (releaseId),
    KEY reviews_by_plugin_user (releaseId, user),
    UNIQUE KEY reviews_by_plugin_user_criteria (releaseId, user, criteria)
);
DROP TABLE IF EXISTS release_watches;
CREATE TABLE release_watches (
    uid INT UNSIGNED REFERENCES users(uid),
    projectId INT UNSIGNED REFERENCES projects(projectId)
);
DROP TABLE IF EXISTS category_watches;
CREATE TABLE category_watches (
    uid INT UNSIGNED REFERENCES users(uid),
    category SMALLINT UNSIGNED NOT NULL
);
DROP TABLE IF EXISTS event_timeline;
CREATE TABLE event_timeline (
    eventId BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type SMALLINT UNSIGNED NOT NULL,
    details VARCHAR(8191)
);
DROP TABLE IF EXISTS user_timeline;
CREATE TABLE user_timeline(
    eventId BIGINT UNSIGNED REFERENCES event_timeline(eventId),
    userId INT UNSIGNED REFERENCES users(uid)
);
DROP TABLE IF EXISTS useronline;
CREATE TABLE `useronline` (
  `timestamp` DECIMAL(16,6) NOT NULL DEFAULT '0',
  `ip` varchar(40) NOT NULL,
  `file` varchar(100) NOT NULL,
  PRIMARY KEY (`timestamp`),
  KEY `ip` (`ip`),
  KEY `file` (`file`)
);
CREATE TABLE rsr_dl_ips (
  resourceId BIGINT UNSIGNED REFERENCES resources(resourceId),
  ip VARCHAR(100), PRIMARY KEY (resourceId, ip)
);
DELIMITER $$
CREATE FUNCTION IncRsrDlCnt (p_resourceId INT, p_ip VARCHAR(56)) RETURNS INT
BEGIN
    DECLARE is_first BIT(1);
    SELECT COUNT(*) = 0 INTO is_first FROM rsr_dl_ips WHERE `resourceId` = p_resourceId AND `ip` = p_ip;
    IF is_first THEN
        UPDATE resources SET dlCount = dlCount + 1 WHERE `resourceId` = p_resourceId;
        INSERT INTO rsr_dl_ips (`resourceId`, `ip`) VALUES (p_resourceId, p_ip);
    END IF;
    RETURN is_first;
END$$
DELIMITER ;

