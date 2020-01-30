-- navid-todo

ALTER TABLE director_datafield ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER format;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (168, NOW());
