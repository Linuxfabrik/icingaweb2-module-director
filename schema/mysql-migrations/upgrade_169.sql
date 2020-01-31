-- navid-todo

ALTER TABLE icinga_command ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER zone_id;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (169, NOW());
