BEGIN;
DELETE FROM blobs;
DELETE FROM owns_on_tape;
DELETE FROM owns_on_cd;
DELETE FROM songs;
DELETE FROM albums;
DELETE FROM artists;
DELETE FROM users_groups;
DELETE FROM groups;
DELETE FROM users;
COMMIT;
ALTER TABLE songs AUTO_INCREMENT = 0;
ALTER TABLE albums AUTO_INCREMENT = 0;
ALTER TABLE artists AUTO_INCREMENT = 0;
ALTER TABLE groups AUTO_INCREMENT = 0;
ALTER TABLE users AUTO_INCREMENT = 0;