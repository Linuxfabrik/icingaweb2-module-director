# Linuxfabrik Fork of the Icinga Director

## Motivation - Why we forked

We manage the Icinga Director configuration of many Icinga2 servers using the Icinga Director configuration basket ex- and import. Currently the basket matches objects by their _name_, which means it is _impossible_ to rename existing objects. Instead, renaming creates a new object with the new name, requiring us to manually delete the object with the old name on each Icinga2 server.

This is particularly problematic with DataFields: If you change any attribute (e.g. the caption or just the description), the original Icinga Director basket creates a _new_ DataField. Assuming you did not change the field name, you now have two DataFields with the same field name. When you delete the first/old one, you are asked if you want to delete the associated vars.

This behaviour is ugly and misleading, and the problem exists because Director baskets do not rely on unique IDs.

We have changed this.


## Features

This fork of the Icinga Director implements exporting and importing of Director baskets based on [UUIDs] (https://en.wikipedia.org/wiki/Universally_unique_identifier).

For the following objects, the UUID is used instead of the object name when exporting and importing via Director baskets:

* DataFields
* Commands
* Service Templates
* Service Sets
* Host Templates
* Notification Templates
* Timeperiods
* Dependencies
* DataLists

This allows the following tasks to be performed by importing a basket

* Changing the name of the objects listed above. Without UUIDs, this would always create a new object instead.
* DataFields: When renaming, any custom variables applied (e.g. to a host) are also renamed.
* DataLists: Entries can be removed from the list. Note: This does not affect applied entries as they are stored as strings in the database. See [Known Limitations] (#known-limitations) below.
* Service Sets: Services can be removed from the set.


## Installation

Follow the [installation instructions](doc/02-Installation.md.d/From-Source.md), then make the necessary changes to the Director MySQL/MariaDB database. Note: If you are currently using our [old fork](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director), make sure to disable the Director module before installing this fork.

If you are currently using the [official (upstream) Icinga Director](https://github.com/Icinga/icingaweb2-module-director), apply the modified schema as follows:
```bash
mysql -p -u root director < schema/add-uuids.sql
```

If you are migrating from our [old fork](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director), follow these steps
* Disable the Director module in IcingaWeb2
* Install this fork
* Apply the required SQL migrations:
```bash
mysql -p -u root director < schema/guids2uuids-migration.sql
```
* Enable the Director


## Known limitations

* Currently the only supported database is MySQL/MariaDB.
* Importing baskets without UUIDs (for the objects listed above) does not work.
* DataFields: Renaming or removing an entry will only rename/remove the entry in the datalist, not the applied variables on other objects such as hosts or services.
* The fork is not tested with [Configuration Branches for Icinga Director](https://icinga.com/docs/icinga-director-branches/latest/).


## Future

* The UUIDs could be used to allow certain objects to be deleted during the basket import, e.g. an obsolete service.
* The basket import could ignore the enabled/disabled state of objects, allowing us to customise the Icinga Director configuration on a system without it being overwritten by an import.
* We are in contact with the Icinga guys about this project.
