ALTER TABLE releases ADD COLUMN assignee INT UNSIGNED;
ALTER TABLE releases ADD FOREIGN KEY (assignee) REFERENCES users(uid);
