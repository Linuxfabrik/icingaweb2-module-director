# Linuxfabrik Fork of the Icinga Director

## Motivation - Why we forked

Have a look at the [previous version](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/uuid-baskets/README.md) for our initial motivation.

Fortunately, we had the possibility to work together with Icinga to integrate most of our changes into the master branch of the official Icinga Director.
However, we are still missing one feature that we need for our deployments: Automatic renaming of applied custom variables.

## Features

This version of our fork:

* is based on the official [v1.12.1 release](https://github.com/Icinga/icingaweb2-module-director/releases/tag/v1.12.1)
* automatically renames applied related vars of Data Fields during basket imports. Have a look at [Testing](#Testing) for details. Custom Variables (the Director's successor to Data Fields) are renamed by the official Director itself.
* fixes https://github.com/Icinga/icingaweb2-module-director/issues/2725


## Installation

Follow the [installation instructions](doc/02-Installation.md.d/From-Source.md).

Migrating from v1.10.2+ or [Linuxfabrik fork v1.10.2.2023020901](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director):
* Install this fork.
* Use the DB-Migrations offered in IcingaWeb2.


## Known limitations

* DataFields: Renaming or removing an entry will only rename/remove the entry in the datalist, not the applied variables on other objects such as hosts or services.
* Data Fields: Applied vars are not renamed during basket imports if the old or the new name belongs to a Custom Variable. Both share the same storage, so renaming would move values that belong to the Custom Variable.
* Data Fields: Do not convert them with `icingacli director migrate datafields` as long as baskets are shared across Director instances. The conversion assigns random UUIDs per instance. A basket still matches a Custom Variable by name, but a renamed Custom Variable is then created as a new one and the applied vars keep the old name.
* The fork is not tested with [Configuration Branches for Icinga Director](https://icinga.com/docs/icinga-director-branches/latest/).


## Testing

* Import [rename-related-vars1.json](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/basket-rename-vars/test/php/library/Director/Objects/json/rename-related-vars1.json)
* Create a host which has the custom variable applied and contains a value: `icingacli director host create host1 --imports ___TEST___host_template1 --vars.___TEST___datafield1 'myvalue1'`
* Import [rename-related-vars2.json](https://github.com/Linuxfabrik/icingaweb2-module-director/blob/feature/basket-rename-vars/test/php/library/Director/Objects/json/rename-related-vars2.json)
* During this import the variable was renamed from `___TEST___datafield1` to `___TEST___datafield1-renamed`.
* Make sure that the applied variable on the host is also renamed: `icingacli director host show host1`
