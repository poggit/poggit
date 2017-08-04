CREATE TABLE virion_usages (
    virionBuild BIGINT UNSIGNED,
    userBuild BIGINT UNSIGNED,
    FOREIGN KEY (virionBuild) REFERENCES builds(buildId) ON DELETE CASCADE,
    FOREIGN KEY (userBuild) REFERENCES builds(buildId) ON DELETE CASCADE
);
