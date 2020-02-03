ALTER TABLE director_datafield ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER format;
ALTER TABLE director_datalist ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER owner;
ALTER TABLE icinga_command ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER zone_id;
ALTER TABLE icinga_dependency ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER parent_service_by_name;
ALTER TABLE icinga_host ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER template_choice_id;
ALTER TABLE icinga_notification ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER assign_filter;
ALTER TABLE icinga_service ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER template_choice_id;
ALTER TABLE icinga_service_set ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER assign_filter;
ALTER TABLE icinga_timeperiod ADD COLUMN guid CHAR(36) DEFAULT NULL AFTER prefer_includes;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (168, NOW());
