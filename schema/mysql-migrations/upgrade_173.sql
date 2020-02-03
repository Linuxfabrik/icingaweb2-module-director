ALTER TABLE icinga_notification ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER assign_filter;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (173, NOW());
