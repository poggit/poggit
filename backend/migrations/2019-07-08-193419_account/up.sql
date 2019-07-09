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
