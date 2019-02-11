CREATE TABLE scanner (
    id INTEGER PRIMARY KEY,
    first_time DATETIME NOT NULL,
    last_time DATETIME NOT NULL,
    domain VARCHAR NOT NULL,
    homeserver VARCHAR NOT NULL,
    srv_record BOOLEAN NOT NULL,
    well_known BOOLEAN NOT NULL,
    software VARCHAR NOT NULL
);
