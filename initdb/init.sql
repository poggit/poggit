CREATE TYPE account_type AS ENUM('Org', 'Guest', 'Beta', 'User');

CREATE TABLE account (
	id VARCHAR(255) PRIMARY KEY,
	name VARCHAR(255) UNIQUE,
	-- acc_type account_type NOT NULL,
	acc_type VARCHAR(5) NOT NULL,
	email VARCHAR(255) NULL,
	first_login TIMESTAMP NULL,
	last_login TIMESTAMP NULL
);
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
CREATE TABLE repo (
	id VARCHAR(255) PRIMARY KEY,
	owner VARCHAR(255) NOT NULL REFERENCES account(id),
	name VARCHAR(255) NOT NULL,
	private BOOL NOT NULL,
	fork BOOL NOT NULL,
	UNIQUE (owner, name)
);
CREATE TABLE project (
	id SERIAL PRIMARY KEY,
	owner VARCHAR(255) NOT NULL REFERENCES account(id),
	repo VARCHAR(255) NOT NULL REFERENCES repo(id),
	name VARCHAR(255) NOT NULL,
	UNIQUE(owner, name)
);
CREATE TABLE artifact (
	id SERIAL PRIMARY KEY,
	mime VARCHAR(255) NOT NULL,
	default_name VARCHAR(255) NOT NULL,
	source VARCHAR(255) NOT NULL,
	downloads INTEGER NOT NULL,
	created TIMESTAMP NOT NULL,
	expiry TIMESTAMP NULL,
	data BYTEA NOT NULL,
	dep_repo VARCHAR(255) NULL REFERENCES repo(id)
);

CREATE INDEX ON artifact(expiry);
-- Your SQL goes here