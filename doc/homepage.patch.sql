CREATE TABLE known_spoons (
    id SMALLINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(16) UNIQUE
);

INSERT INTO known_spoons (name) VALUES
    ('1.0.0'),
    ('1.1.0'),
    ('1.2.1'),
    ('1.3.0'),
    ('1.3.1'),
    ('1.4.0'),
    ('1.4.1'),
    ('1.5.0'),
    ('1.6.0'),
    ('1.6.1'),
    ('1.7.0'),
    ('1.7.1'),
    ('1.8.0'),
    ('1.9.0'),
    ('1.10.0'),
    ('1.11.0'),
    ('1.12.0'),
    ('1.13.0'),
    ('2.0.0'),
    ('2.1.0'),
    ('3.0.0-ALPHA1'),
    ('3.0.0-ALPHA2'),
    ('3.0.0-ALPHA3'),
    ('3.0.0-ALPHA4'),
    ('3.0.0-ALPHA5'),
    ('3.0.0-ALPHA6'),
    ('3.0.0-ALPHA7');

ALTER TABLE release_spoons ADD FOREIGN KEY (since) REFERENCES known_spoons(name);
ALTER TABLE release_spoons ADD FOREIGN KEY (till) REFERENCES known_spoons(name);
