ALTER TABLE icinga_dependency ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER parent_service_by_name;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (175, NOW());
