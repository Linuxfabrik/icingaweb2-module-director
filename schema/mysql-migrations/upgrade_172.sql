ALTER TABLE icinga_host ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER template_choice_id;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (172, NOW());
