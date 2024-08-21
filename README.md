# Linuxfabrik Fork of the Icinga Director

## Motivation - Why we forked

Have a look at the [previous version](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/uuid-baskets/README.md) for our initial motivation.

Fortunately, we had the possibility to work together with Icinga to integrate most of our changes into the master branch of the official Icinga Director.
However, we are still missing one feature that we need for our deployments: Automatic renaming of applied custom variables.

## Features

This version of our fork:

* is based on the official [v1.11.1 release](https://github.com/Icinga/icingaweb2-module-director/releases/tag/v1.11.1) (on the [support/1.11 branch](https://github.com/Icinga/icingaweb2-module-director/tree/support/1.11))
* automatically renames applied related vars during basket imports. Have a look at [Testing](#Testing) for details.
* fixes https://github.com/Icinga/icingaweb2-module-director/issues/2725
* makes the MySQL migrations "nicer" - they do not fail if the uuid columns already exist (making migrations easier)


## Installation

Follow the [installation instructions](doc/02-Installation.md.d/From-Source.md).

Migrating from [Linuxfabrik fork v1.8.1 or lower](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director):
* Important: Disable the Director module in IcingaWeb2 before installing this fork.
* Install this fork.
* Apply the required SQL migrations:
```bash
mysql -p -u root icinga_director < schema/guids2uuids-migration.sql
```
* Enable the Director.

Migrating from v1.10.2+ or [Linuxfabrik fork v1.10.2.2023020901](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director):
* Install this fork.
* Use the DB-Migrations offered in IcingaWeb2.


## Known limitations

* DataFields: Renaming or removing an entry will only rename/remove the entry in the datalist, not the applied variables on other objects such as hosts or services.
* The fork is not tested with [Configuration Branches for Icinga Director](https://icinga.com/docs/icinga-director-branches/latest/).


## Testing

* Import [rename-related-vars1.json](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/basket-rename-vars/test/php/library/Director/Objects/json/rename-related-vars1.json)
* Create a host which has the custom variable applied and contains a value: `icingacli director host create host1 --imports ___TEST___host_template1 --vars.___TEST___datafield1 'myvalue1'`
* Import [rename-related-vars2.json](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/basket-rename-vars/test/php/library/Director/Objects/json/rename-related-vars2.json)
* During this import the variable was renamed from `___TEST___datafield1` to `___TEST___datafield1-renamed`.
* Make sure that the applied variable on the host is also renamed: `icingacli director host show host1`
