ALTER TABLE icinga_service_set ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER assign_filter;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (171, NOW());
