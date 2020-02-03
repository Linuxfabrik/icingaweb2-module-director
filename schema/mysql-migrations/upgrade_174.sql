ALTER TABLE icinga_timeperiod ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER prefer_includes;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (174, NOW());
