-- CREATE DATABASE `poggit` /*!40100 DEFAULT CHARACTER SET latin1 */ /*!80016 DEFAULT ENCRYPTION='N' */;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `uid`       int unsigned NOT NULL PRIMARY KEY,
    `name`      varchar(255) UNIQUE,
    `token`     varchar(64),
    `scopes`    varchar(511) DEFAULT '',
    `email`     varchar(255) DEFAULT '',
    `lastLogin` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `lastNotif` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `opts`      varchar(16383) DEFAULT '{}'
);
DROP TABLE IF EXISTS `user_ips`;
CREATE TABLE `user_ips` (
    `uid`   int unsigned NOT NULL,
    `ip`    varchar(100) NOT NULL,
    `time`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`uid`,`ip`),
    FOREIGN KEY (`uid`) REFERENCES `users` (`uid`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `repos`;
CREATE TABLE `repos` (
    `repoId`        int unsigned NOT NULL PRIMARY KEY,
    `owner`         varchar(256) NOT NULL,
    `name`          varchar(256) NOT NULL,
    `private`       tinyint(1) NOT NULL DEFAULT '0',
    `build`         tinyint(1) NOT NULL DEFAULT '0',
    `fork`          tinyint(1) NOT NULL,
    `accessWith`    int unsigned NOT NULL,
    `webhookId`     bigint unsigned NOT NULL,
    `webhookKey`    binary(8) NOT NULL,
    KEY `full_name` (`owner`,`name`)
);
DROP TABLE IF EXISTS `submit_rules`;
CREATE TABLE `submit_rules` (
    `id`        varchar(10) NOT NULL PRIMARY KEY,
    `title`     varchar(1000),
    `content`   text,
    `uses`      int DEFAULT '0'
);
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
    `projectId` int unsigned NOT NULL PRIMARY KEY,
    `repoId`    int unsigned NOT NULL,
    `name`      varchar(255) NOT NULL,
    `path`      varchar(1000) NOT NULL,
    `type`      tinyint unsigned NOT NULL, -- Plugin = 0, Library = 1
    `framework` varchar(100) NOT NULL, -- default, nowhere
    `lang`      tinyint(1) NOT NULL,
    UNIQUE KEY `repo_proj` (`repoId`,`name`),
    FOREIGN KEY (`repoId`) REFERENCES `repos` (`repoId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `project_subs`;
CREATE TABLE `project_subs` (
    `projectId` int unsigned,
    `userId`    int unsigned,
    `level`     tinyint DEFAULT '1',
    UNIQUE KEY `user_project` (`userId`,`projectId`)
);
DROP TABLE IF EXISTS `resources`;
CREATE TABLE `resources` (
    `resourceId`    bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `type`          varchar(100) NOT NULL, -- phar, md, png, zip, etc.
    `mimeType`      varchar(100) NOT NULL,
    `created`       timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `accessFilters` json NOT NULL,
    `dlCount`       bigint NOT NULL DEFAULT '0',
    `duration`      int unsigned NOT NULL,
    `relMd`         bigint unsigned,
    `src`           varchar(40),
    `fileSize`      int NOT NULL DEFAULT '-1'
) AUTO_INCREMENT = 2;
INSERT INTO resources (resourceId, type, mimeType, accessFilters, dlCount, duration, fileSize)
VALUES (1, '', 'text/plain', '[]', 0, 315360000, 0);
DROP TABLE IF EXISTS `builds`;
CREATE TABLE `builds` (
    `buildId`           bigint unsigned NOT NULL PRIMARY KEY,
    `resourceId`        bigint unsigned,
    `projectId`         int unsigned,
    `class`             tinyint, -- Dev = 1, PR = 4
    `branch`            varchar(255) DEFAULT 'master',
    `sha`               char(40),
    `cause`             varchar(8191),
    `internal`          int, -- internal (project,class) build number, as opposed to global build number
    `created`           timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `triggerUser`       int unsigned DEFAULT '0', -- not necessarily REFERENCES users(uid), because may not have registered on Poggit yet
    `logRsr`            bigint unsigned DEFAULT '1',
    `path`              varchar(1000),
    `main`              varchar(255),
    `buildsAfterThis`   smallint DEFAULT '0', -- a temporary column for checking build completion
    KEY `builds_by_project` (`projectId`),
    KEY `logRsr` (`logRsr`),
    FOREIGN KEY (`projectId`) REFERENCES `projects` (`projectId`) ON DELETE CASCADE,
    FOREIGN KEY (`logRsr`) REFERENCES `resources` (`resourceId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `builds_statuses`;
CREATE TABLE `builds_statuses` (
    `buildId`   bigint unsigned,
    `level`     tinyint NOT NULL,
    `class`     varchar(255) NOT NULL,
    `body`      text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    KEY `statuses_by_build` (`buildId`),
    KEY `statuses_by_level` (`buildId`,`level`),
    FOREIGN KEY (`buildId`) REFERENCES `builds` (`buildId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `virion_builds`;
CREATE TABLE `virion_builds` (
    `buildId`   bigint unsigned NOT NULL PRIMARY KEY,
    `version`   varchar(255) NOT NULL,
    `api`       varchar(255) NOT NULL, -- JSON-encoded
    FOREIGN KEY (`buildId`) REFERENCES `builds` (`buildId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `virion_usages`;
CREATE TABLE `virion_usages` (
    `virionBuild`   bigint unsigned NOT NULL,
    `userBuild`     bigint unsigned NOT NULL,
    FOREIGN KEY (`virionBuild`) REFERENCES `builds` (`buildId`) ON DELETE CASCADE,
    FOREIGN KEY (`userBuild`) REFERENCES `builds` (`buildId`) ON DELETE CASCADE
);
CREATE OR REPLACE VIEW `recent_virion_usages` AS
select `virion_build`.`projectId` AS `virionProject`,`user_build`.`projectId` AS `userProject`,
       (unix_timestamp() - max(unix_timestamp(`user_build`.`created`))) AS `sinceLastUse`
from ((`virion_usages`
    join `builds` `virion_build` on((`virion_usages`.`virionBuild` = `virion_build`.`buildId`)))
    join `builds` `user_build` on((`virion_usages`.`userBuild` = `user_build`.`buildId`)))
group by `virion_build`.`projectId`,`user_build`.`projectId`
order by `sinceLastUse`;
DROP TABLE IF EXISTS `namespaces`;
CREATE TABLE `namespaces` (
    `nsid`      int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`      varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL UNIQUE,
    `parent`    int unsigned,
    `depth`     tinyint unsigned NOT NULL,
    KEY `ns_by_depth` (`depth`)
) AUTO_INCREMENT = 2;
DROP TABLE IF EXISTS `known_classes`;
CREATE TABLE `known_classes` (
    `clid`      int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `parent`    int unsigned NOT NULL,
    `name`      varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
    UNIQUE KEY `cl_by_fqn` (`parent`,`name`)
) AUTO_INCREMENT = 2;
DROP TABLE IF EXISTS `class_occurrences`;
CREATE TABLE `class_occurrences` (
    `clid`      int unsigned NOT NULL,
    `buildId`   bigint unsigned NOT NULL
);
DROP TABLE IF EXISTS `known_spoons`;
CREATE TABLE `known_spoons` (
    `id`            smallint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`          varchar(16) UNIQUE,
    `incompatible`  tinyint(1) NOT NULL
);
DROP TABLE IF EXISTS `releases`;
CREATE TABLE `releases` (
    `releaseId`             int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`                  varchar(255),
    `shortDesc`             varchar(255) DEFAULT '',
    `artifact`              bigint unsigned,
    `projectId`             int unsigned,
    `buildId`               bigint unsigned,
    `version`               varchar(100),
    `description`           bigint unsigned,
    `descriptionType`       varchar(4),
    `descriptionMarkdown`   longtext,
    `descriptionText`       longtext,
    `icon`                  varchar(511),
    `changelog`             bigint unsigned,
    `changelogText`         longtext,
    `license`               varchar(100),
    `licenseRes`            bigint DEFAULT '1',
    `licenseText`           longtext,
    `flags`                 smallint DEFAULT '0',
    `creation`              timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `state`                 tinyint DEFAULT '0',
    `updateTime`            timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `parent_releaseId`      int unsigned,
    `assignee`              int unsigned,
    `was_checked`           tinyint(1) DEFAULT '0',
    `adminNote`             text,
    KEY `releases_by_project` (`projectId`),
    KEY `releases_by_name` (`name`),
    KEY `buildId` (`buildId`),
    KEY `assignee` (`assignee`),
    FOREIGN KEY (`projectId`) REFERENCES `projects` (`projectId`) ON DELETE CASCADE,
    FOREIGN KEY (`assignee`) REFERENCES `users` (`uid`) ON DELETE RESTRICT
);
DROP TABLE IF EXISTS `release_authors`;
CREATE TABLE `release_authors` (
    `projectId` int unsigned,
    `uid`       int unsigned, -- may not be registered on Poggit
    `name`      varchar(32),
    `level`     tinyint, -- collaborator = 1, contributor = 2, translator = 3, requester = 4
    UNIQUE KEY `projectId` (`projectId`,`uid`),
    FOREIGN KEY (`projectId`) REFERENCES `projects` (`projectId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `release_categories`;
CREATE TABLE `release_categories` (
    `projectId`         int unsigned,
    `category`          smallint unsigned NOT NULL,
    `isMainCategory`    tinyint(1) NOT NULL DEFAULT '0',
    UNIQUE KEY `projectId_2` (`projectId`,`category`),
    KEY `projectId` (`projectId`),
    FOREIGN KEY (`projectId`) REFERENCES `projects` (`projectId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `release_keywords`;
CREATE TABLE `release_keywords` (
    `projectId` int unsigned,
    `word`      varchar(100) NOT NULL,
    KEY `projectId` (`projectId`),
    FOREIGN KEY (`projectId`) REFERENCES `projects` (`projectId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `spoon_prom`;
CREATE TABLE `spoon_prom` (
    `name`  varchar(50) NOT NULL PRIMARY KEY,
    `value` varchar(16),
    KEY `value` (`value`),
    FOREIGN KEY (`value`) REFERENCES `known_spoons` (`name`)
);
DROP TABLE IF EXISTS `release_spoons`;
CREATE TABLE `release_spoons` (
    `releaseId` int unsigned,
    `since` varchar(16),
    `till` varchar(16),
    KEY `releaseId` (`releaseId`),
    KEY `since` (`since`),
    KEY `till` (`till`),
    FOREIGN KEY (`releaseId`) REFERENCES `releases` (`releaseId`) ON DELETE CASCADE,
    FOREIGN KEY (`since`) REFERENCES `known_spoons` (`name`),
    FOREIGN KEY (`till`) REFERENCES `known_spoons` (`name`)
);
DROP TABLE IF EXISTS `release_deps`;
CREATE TABLE `release_deps` (
    `releaseId` int unsigned,
    `name`      varchar(100) NOT NULL,
    `version`   varchar(100) NOT NULL,
    `depRelId`  int unsigned,
    `isHard`    tinyint(1) NOT NULL,
    KEY `releaseId` (`releaseId`),
    FOREIGN KEY (`releaseId`) REFERENCES `releases` (`releaseId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `release_reqr`;
CREATE TABLE `release_reqr` (
    `releaseId` int unsigned,
    `type`      tinyint,
    `details`   varchar(255) DEFAULT '',
    `isRequire` tinyint(1) NOT NULL,
    KEY `releaseId` (`releaseId`),
    FOREIGN KEY (`releaseId`) REFERENCES `releases` (`releaseId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `release_perms`;
CREATE TABLE `release_perms` (
    `releaseId` int unsigned,
    `val`       tinyint,
    KEY `release_meta_index` (`releaseId`),
    FOREIGN KEY (`releaseId`) REFERENCES `releases` (`releaseId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `release_reviews`;
CREATE TABLE `release_reviews` (
    `reviewId`  int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `releaseId` int unsigned,
    `user`      int unsigned,
    `criteria`  int unsigned,
    `type`      tinyint unsigned, -- Official = 1, User = 2, Robot = 3
    `cat`       tinyint unsigned, -- perspective: code? test?
    `score`     smallint unsigned,
    `message`   varchar(8191) DEFAULT '',
    `created`   timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `reviews_by_plugin_user_criteria` (`releaseId`,`user`,`criteria`),
    KEY `reviews_by_plugin` (`releaseId`),
    KEY `reviews_by_plugin_user` (`releaseId`,`user`),
    FOREIGN KEY (`releaseId`) REFERENCES `releases` (`releaseId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `release_reply_reviews`;
CREATE TABLE `release_reply_reviews` (
    `reviewId`  int unsigned NOT NULL,
    `user`      int unsigned NOT NULL,
    `message`   varchar(8191),
    `created`   timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`reviewId`,`user`),
    FOREIGN KEY (`reviewId`) REFERENCES `release_reviews` (`reviewId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `event_timeline`;
CREATE TABLE `event_timeline` (
    `eventId`   bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `created`   timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type`      smallint unsigned NOT NULL,
    `details`   varchar(8191)
) AUTO_INCREMENT = 1;
INSERT INTO event_timeline (type, details)
VALUES (1, '{"eventId":null,"created":null}');
DROP TABLE IF EXISTS `user_timeline`;
CREATE TABLE `user_timeline` (
    `eventId`   bigint unsigned,
    `userId`    int unsigned
);
DROP TABLE IF EXISTS `users_online`;
CREATE TABLE `users_online` (
    `ip`        varchar(40) NOT NULL PRIMARY KEY,
    `lastOn`    timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
DROP TABLE IF EXISTS `rsr_dl_ips`;
CREATE TABLE `rsr_dl_ips` (
    `resourceId`    bigint unsigned NOT NULL,
    `ip`            varchar(100) NOT NULL,
    `latest`        timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `count`         int NOT NULL DEFAULT '1',
    PRIMARY KEY (`resourceId`,`ip`),
    FOREIGN KEY (`resourceId`) REFERENCES `resources` (`resourceId`) ON DELETE CASCADE
);
DROP TABLE IF EXISTS `ext_refs`;
CREATE TABLE `ext_refs` (
    `srcDomain` varchar(255) NOT NULL PRIMARY KEY,
    `cnt`       bigint DEFAULT '1'
);

-- Default data needed for out of the box to work, use spoons.edit to add/edit more
INSERT INTO `known_spoons` (`id`, `name`, `incompatible`) VALUES
    (0, '5.0.0', true),
    (1, '5.1.0', false),
    (2, '5.2.0', false),
    (3, '5.15.0', false);

INSERT INTO `spoon_prom` (`name`, `value`) VALUES
    ('poggit.pmapis.promoted', '5.15.0'),
    ('poggit.pmapis.promotedCompat', '5.0.0'),
    ('poggit.pmapis.latest', '5.15.0'),
    ('poggit.pmapis.latestCompat', '5.0.0');

DELIMITER $$
CREATE FUNCTION `IncRsrDlCnt`(p_resourceId BIGINT UNSIGNED, p_ip VARCHAR(56)) RETURNS int
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
CREATE FUNCTION `KeepOnline`(p_ip VARCHAR(40), p_uid INT UNSIGNED) RETURNS int
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
    WHERE UNIX_TIMESTAMP() - UNIX_TIMESTAMP(lastOn) > 300;

    SELECT COUNT(*)
    INTO cnt
    FROM users_online;

    RETURN cnt;
END $$

-- for humans only
CREATE PROCEDURE `BumpApi`(IN api_id SMALLINT)
BEGIN
    CREATE TEMPORARY TABLE bumps (
        rid INT UNSIGNED
    );
    INSERT INTO bumps (rid)
    SELECT releaseId
    FROM (SELECT r.releaseId, r.flags & 4 outdated, MAX(k.id) max
          FROM releases r
                   LEFT JOIN release_spoons s ON r.releaseId = s.releaseId
                   INNER JOIN known_spoons k ON k.name = s.till
          GROUP BY r.releaseId
          HAVING outdated = 0 AND max < api_id) t;
    UPDATE releases SET flags = flags | 4 WHERE EXISTS(SELECT rid FROM bumps WHERE rid = releaseId);
    DROP TABLE bumps;
END $$
CREATE PROCEDURE `MergeExtRef`(IN to_name VARCHAR(255), IN from_pattern VARCHAR(255))
BEGIN
    DECLARE to_add BIGINT;

    SELECT SUM(cnt)
    INTO to_add
    FROM ext_refs
    WHERE srcDomain LIKE from_pattern;

    UPDATE ext_refs
    SET cnt = cnt + to_add
    WHERE srcDomain = to_name;

    DELETE FROM ext_refs
    WHERE srcDomain LIKE from_pattern;
END $$
DELIMITER ;
