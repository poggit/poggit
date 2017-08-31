ALTER TABLE builds ADD COLUMN path VARCHAR(1000) AFTER logRsr;
UPDATE builds SET path = (SELECT path FROM projects WHERE projects.projectId = builds.projectId);
