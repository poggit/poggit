CREATE TABLE login_history (
	token CHAR(40) PRIMARY KEY,
	account VARCHAR(255) NULL REFERENCES account (id),
	ip VARCHAR(255) NOT NULL,
	target VARCHAR(255) NOT NULL,
	request_time TIMESTAMP NOT NULL,
	success_time TIMESTAMP NOT NULL
);

CREATE INDEX ON login_history (ip);
CREATE INDEX ON login_history (request_time);
