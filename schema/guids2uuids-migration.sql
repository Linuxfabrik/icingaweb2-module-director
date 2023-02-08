ALTER TABLE director_datafield DROP COLUMN IF EXISTS uuid;
ALTER TABLE director_datafield MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE director_datafield set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE director_datafield CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);

ALTER TABLE director_datalist DROP COLUMN IF EXISTS uuid;
ALTER TABLE director_datalist MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE director_datalist set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE director_datalist CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);

ALTER TABLE icinga_command DROP COLUMN IF EXISTS uuid;
ALTER TABLE icinga_command MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE icinga_command set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE icinga_command CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);

ALTER TABLE icinga_dependency DROP COLUMN IF EXISTS uuid;
ALTER TABLE icinga_dependency MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE icinga_dependency set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE icinga_dependency CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);

ALTER TABLE icinga_host DROP COLUMN IF EXISTS uuid;
ALTER TABLE icinga_host MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE icinga_host set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE icinga_host CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);

ALTER TABLE icinga_notification DROP COLUMN IF EXISTS uuid;
ALTER TABLE icinga_notification MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE icinga_notification set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE icinga_notification CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);

ALTER TABLE icinga_service DROP COLUMN IF EXISTS uuid;
ALTER TABLE icinga_service MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE icinga_service set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE icinga_service CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);

ALTER TABLE icinga_service_set DROP COLUMN IF EXISTS uuid;
ALTER TABLE icinga_service_set MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE icinga_service_set set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE icinga_service_set CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);

ALTER TABLE icinga_timeperiod DROP COLUMN IF EXISTS uuid;
ALTER TABLE icinga_timeperiod MODIFY COLUMN guid VARBINARY(36) NOT NULL;
UPDATE icinga_timeperiod set guid=UNHEX(REPLACE(guid,'-',''));
ALTER TABLE icinga_timeperiod CHANGE COLUMN IF EXISTS guid uuid VARBINARY(16) NOT NULL AFTER id, ADD UNIQUE INDEX IF NOT EXISTS uuid (uuid);
