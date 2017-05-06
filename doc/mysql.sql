DROP TABLE IF EXISTS users;
CREATE TABLE users (
    uid INT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) UNIQUE,
    token VARCHAR(64),
    opts VARCHAR(16383) DEFAULT '{}'
);
DROP TABLE IF EXISTS user_ips;
CREATE TABLE user_ips (
    uid INT UNSIGNED,
    ip VARCHAR(100),
    time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(uid, ip),
    FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
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
    repoId INT UNSIGNED,
    name VARCHAR(255),
    path VARCHAR(1000),
    type TINYINT UNSIGNED, -- Plugin = 0, Library = 1
    framework VARCHAR(100), -- default, nowhere
    lang BIT(1),
    UNIQUE KEY repo_proj (repoId, name),
    FOREIGN KEY (repoId) REFERENCES repos(repoId) ON DELETE CASCADE
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
    relMd BIGINT UNSIGNED DEFAULT NULL REFERENCES resources(resourceId),
    src VARCHAR(40)
) AUTO_INCREMENT=2;
INSERT INTO resources (resourceId, type, mimeType, accessFilters, dlCount, duration) VALUES
    (1, '', 'text/plain', '[]', 0, 315360000);
DROP TABLE IF EXISTS builds;
CREATE TABLE builds (
    buildId BIGINT UNSIGNED PRIMARY KEY,
    resourceId BIGINT UNSIGNED REFERENCES resources(resourceId),
    projectId INT UNSIGNED,
    class TINYINT, -- Dev = 1, PR = 4
    branch VARCHAR(255) DEFAULT 'master',
    sha CHAR(40),
    cause VARCHAR(8191),
    internal INT, -- internal (project,class) build number, as opposed to global build number
    created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    triggerUser INT UNSIGNED DEFAULT 0, -- not necessarily REFERENCES users(uid), because may not have registered on Poggit yet
    logRsr BIGINT UNSIGNED DEFAULT 1,
    KEY builds_by_project (projectId),
    FOREIGN KEY (projectId) REFERENCES projects(projectId) ON DELETE CASCADE,
    FOREIGN KEY (logRsr) REFERENCES resources(resourceId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS builds_statuses;
CREATE TABLE builds_statuses (
    buildId BIGINT UNSIGNED,
    level TINYINT NOT NULL,
    class VARCHAR(255) NOT NULL,
    body VARCHAR(8101) DEFAULT '{}' NOT NULL,
    KEY statuses_by_build(buildId),
    KEY statuses_by_level(buildId, level),
    FOREIGN KEY (buildId) REFERENCES builds(buildId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS virion_builds;
CREATE TABLE virion_builds (
    buildId BIGINT UNSIGNED,
    version VARCHAR(255) NOT NULL,
    api VARCHAR(255) NOT NULL, -- JSON-encoded
    FOREIGN KEY (buildId) REFERENCES builds(buildId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS namespaces;
CREATE TABLE namespaces (
    nsid INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    parent INT UNSIGNED DEFAULT NULL REFERENCES namespaces(id),
    depth TINYINT UNSIGNED NOT NULL,
    KEY ns_by_depth(depth)
) AUTO_INCREMENT = 2;
DROP TABLE IF EXISTS known_classes;
CREATE TABLE known_classes (
    clid INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    parent INT UNSIGNED DEFAULT NULL REFERENCES namespaces(id),
    name VARCHAR(255),
    KEY cl_by_parent(parent),
    UNIQUE KEY cl_by_fqn(parent, name)
) AUTO_INCREMENT = 2;
DROP TABLE IF EXISTS class_occurrences;
CREATE TABLE class_occurrences (
    clid INT UNSIGNED REFERENCES known_classes(clid),
    buildId BIGINT UNSIGNED,
    FOREIGN KEY (buildId) REFERENCES builds(buildId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS releases;
CREATE TABLE releases (
    releaseId INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    shortDesc VARCHAR(255) DEFAULT '',
    artifact BIGINT UNSIGNED REFERENCES resources(resourceId),
    projectId INT UNSIGNED,
    buildId BIGINT UNSIGNED REFERENCES builds(buildId),
    version VARCHAR(100), -- user-defined version ID, may duplicate
    description BIGINT UNSIGNED REFERENCES resources(resourceId),
    icon VARCHAR(511) DEFAULT NULL, -- url to GitHub raw
    changelog BIGINT UNSIGNED REFERENCES resources(resourceId),
    license VARCHAR(100), -- name of license, or 'file'
    licenseRes BIGINT DEFAULT 1, -- resourceId of license, only set if `license` is set to 'file'
    flags SMALLINT DEFAULT 0, -- for example, featured
    creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    state TINYINT DEFAULT 0,
    updateTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY releases_by_project (projectId),
    KEY releases_by_name (name),
    FOREIGN KEY (projectId) REFERENCES projects(projectId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS release_categories;
CREATE TABLE release_categories (
    projectId INT UNSIGNED,
    category SMALLINT UNSIGNED NOT NULL,
    isMainCategory BIT(1),
    FOREIGN KEY (projectId) REFERENCES projects(projectId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS release_keywords;
CREATE TABLE release_keywords (
    projectId INT UNSIGNED,
    word VARCHAR(100) NOT NULL,
    FOREIGN KEY (projectId) REFERENCES projects(projectId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS release_spoons;
CREATE TABLE release_spoons (
    releaseId INT UNSIGNED,
    since VARCHAR(16),
    till VARCHAR(16),
    FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS release_deps;
CREATE TABLE release_deps (
    releaseId INT UNSIGNED,
    name VARCHAR(100) NOT NULL,
    version VARCHAR(100) NOT NULL,
    depRelId INT UNSIGNED DEFAULT NULL,
    isHard BIT(1),
    FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS release_reqr;
CREATE TABLE release_reqr (
    releaseId INT UNSIGNED,
    type TINYINT,
    details VARCHAR(255) DEFAULT '',
    isRequire BIT(1),
    FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE
);
CREATE TABLE `release_perms` (
    releaseId INT UNSIGNED DEFAULT NULL,
    type TINYINT UNSIGNED DEFAULT NULL,
    val TINYINT DEFAULT NULL,
    KEY release_meta_index (releaseId, type),
    FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS release_reviews;
CREATE TABLE release_reviews (
    reviewId INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    releaseId INT UNSIGNED,
    user INT UNSIGNED REFERENCES users(uid),
    criteria INT UNSIGNED,
    type TINYINT UNSIGNED, -- Official = 1, User = 2, Robot = 3
    cat TINYINT UNSIGNED, -- perspective: code? test?
    score SMALLINT UNSIGNED,
    message VARCHAR(8191) DEFAULT '',
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY reviews_by_plugin (releaseId),
    KEY reviews_by_plugin_user (releaseId, user),
    UNIQUE KEY reviews_by_plugin_user_criteria (releaseId, user, criteria),
    FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS release_reply_reviews;
CREATE TABLE release_reply_reviews (
    reviewId INT UNSIGNED,
    user INT UNSIGNED,
    message VARCHAR(8191),
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(reviewId, user),
    FOREIGN KEY (reviewId) REFERENCES release_reviews(reviewId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS release_votes;
CREATE TABLE release_votes (
    user INT UNSIGNED REFERENCES users(uid),
    releaseId INT UNSIGNED REFERENCES releases(releaseId),
    vote TINYINT,
    message VARCHAR(255) DEFAULT '',
    updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_releaseId (user, releaseId),
    FOREIGN KEY (releaseId) REFERENCES releases(releaseId) ON DELETE CASCADE
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
) AUTO_INCREMENT = 1;
INSERT INTO event_timeline (type, details) VALUES (1, '{}');
DROP TABLE IF EXISTS user_timeline;
CREATE TABLE user_timeline(
    eventId BIGINT UNSIGNED REFERENCES event_timeline(eventId),
    userId INT UNSIGNED REFERENCES users(uid)
);
DROP TABLE IF EXISTS useronline;
CREATE TABLE `useronline` (
    timestamp DECIMAL(16,6) NOT NULL DEFAULT '0',
    ip VARCHAR(40) NOT NULL,
    file VARCHAR(100) NOT NULL
);
DROP TABLE IF EXISTS rsr_dl_ips;
CREATE TABLE rsr_dl_ips (
    resourceId BIGINT UNSIGNED,
    ip VARCHAR(100), PRIMARY KEY (resourceId, ip),
    FOREIGN KEY (resourceId) REFERENCES resources(resourceId) ON DELETE CASCADE
);
DROP TABLE IF EXISTS ext_refs;
CREATE TABLE ext_refs (
    srcDomain VARCHAR(255) PRIMARY KEY,
    cnt BIGINT DEFAULT 1
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
