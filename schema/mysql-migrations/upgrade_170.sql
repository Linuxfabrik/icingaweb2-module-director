ALTER TABLE icinga_service ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER template_choice_id;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (170, NOW());
