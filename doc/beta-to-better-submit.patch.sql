ALTER TABLE releases ADD COLUMN parent_releaseId INT UNSIGNED;
CREATE TABLE release_authors (
    projectId INT UNSIGNED,
    uid INT UNSIGNED,
    name VARCHAR(32),
    level TINYINT,
    UNIQUE KEY (projectId, uid),
    FOREIGN KEY (projectId) REFERENCES projects (projectId)
);
ALTER TABLE release_perms DROP COLUMN type;
