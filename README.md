# Linuxfabrik Fork of the Icinga Director

## Motivation - Why we forked

Have a look at the [previous version](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/uuid-baskets/README.md) for our initial motivation.

Fortunately, we had the possibility to work together with Icinga to integrate most of our changes into the master branch of the official Icinga Director.
However, we are still missing one feature that we need for our deployments: Automatic renaming of applied custom variables.

## Features

This version of our fork:

* is based on the official master branch (commit [35e90f7b6008075bb6d61a55fe12988df3c8b5c7](https://github.com/Icinga/icingaweb2-module-director/tree/35e90f7b6008075bb6d61a55fe12988df3c8b5c7))
* automatically renames applied related vars during basket imports. Have a look at [Testing](#Testing) for details.
* fixes https://github.com/Icinga/icingaweb2-module-director/issues/2725
* fixes https://github.com/Icinga/icingaweb2-module-director/issues/2734
* makes the MySQL migrations "nicer" - they do not fail if the uuid columns already exist (making migrations easier)


## Installation

Follow the [installation instructions](doc/02-Installation.md.d/From-Source.md). Note: If you are currently using our [old fork](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director), make sure to disable the Director module before installing this fork.

If you are migrating from our [old fork](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director) (up to v1.8.1), follow these steps
* Disable the Director module in IcingaWeb2
* Install this fork
* Apply the required SQL migrations:
```bash
mysql -p -u root icinga_director < schema/guids2uuids-migration.sql
```
* Enable the Director


## Known limitations

* Since the fork is based on the master branch instead of a full release, there are still some open upstream bugs to be expected.
* DataFields: Renaming or removing an entry will only rename/remove the entry in the datalist, not the applied variables on other objects such as hosts or services.
* The fork is not tested with [Configuration Branches for Icinga Director](https://icinga.com/docs/icinga-director-branches/latest/).


## Testing

* Import [rename-related-vars1.json](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/basket-rename-vars/test/php/library/Director/Objects/json/rename-related-vars1.json)
* Create a host which has the custom variable applied and contains a value: `icingacli director host create host2 --imports ___TEST___host_template1 --vars.___TEST___datafield1 'myvalue1'`
* Import [rename-related-vars2.json](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/basket-rename-vars/test/php/library/Director/Objects/json/rename-related-vars2.json)
* During this import the variable was renamed from `___TEST___datafield1` to `___TEST___datafield1-renamed`.
* Make sure that the applied variable on the host is also renamed: `icingacli director host show host1`
