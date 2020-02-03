ALTER TABLE director_datalist ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER owner;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (176, NOW());
