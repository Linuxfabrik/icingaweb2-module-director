# Linuxfabrik Fork of the Icinga Director

## Motivation - Why we forked

We are managing the Icinga Director configuration of many Icinga2 Servers using the Icinga Director Basket Ex- and Import. Currently the Basket matches objects using its _name_, which means that it is _impossible_ to rename existing objects. Instead, if renaming, a new object is created with the new name, requiring us to manually delete the object with the old name on every Icinga2 Server.

This is especially problematic with DataFields: If you change any attribute (for example the caption or just the description) the original Icinga Director Basket creates a _new_ DataField. Assuming one did not modify the field name, we now have two DataFields with the same field name. When deleting the first/old one, you will be prompted if the related vars should be wiped.

This behavior is ugly and misleading, and the problem exists because the Director Baskets are not relying on unique IDs.

We changed that.


## Features

This fork of the Icinga Director implements the exporting and importing of Director Baskets based on [UUIDs](https://en.wikipedia.org/wiki/Universally_unique_identifier).

For the following objects the UUID will be used instead of the object name during the ex- and import via Director Baskets:

* DataFields
* Commands
* Service Templates
* Service Sets
* Host Templates
* Notification Templates
* Timeperiods
* Dependencies
* DataLists

This allows the following tasks to be accomplished by importing a Basket:

* Changing the name of the objects listed above. Without UUIDs, this would always create a new object instead.
* DataFields: When renaming, all applied custom variables (for example to a host) will be renamed as well.
* DataLists: Entries can be removed from the list. Note: This does not affect applied entries, as they are saved as strings in the database. See [Known Limitations](#known-limitations) below.
* Service Sets: Services can be removed from the set.


## Installation

Follow the [Installation instructions](doc/02-Installation.md.d/From-Source.md), afterwards apply the required modifications to the Director MySQL/MariaDB database. Note: if you are currently using our [old fork](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director), make sure to disable the Director module before installing this fork.

If you are currently using the [official (upstream) Icinga Director](https://github.com/Icinga/icingaweb2-module-director), apply the modified schema as follows:
```bash
mysql -p -u root director < schema/add-uuids.sql
```

If you are migrating from our [old fork](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director), follow these steps:
* disable the Director module in IcingaWeb2
* install this fork
* apply the required SQL migrations:
```bash
mysql -p -u root director < schema/guuids2uuids-migration.sql
```


## Known limitations

* Currently the only supported database is MySQL/MariaDB.
* Importing Baskets without UUIDs (for the objects listed above) does not work.
* DataFields: Renaming or removing an entry will only rename/remove the entry in the datalist, not the applied variables on other objects such as hosts or services.
* The fork is not tested with [Configuration Branches for Icinga Director](https://icinga.com/docs/icinga-director-branches/latest/).


## Future

* The UUIDs could be used to allow deletion of certain objects during the basket import, for example a deprecated service.
* The basket import could ignore the enabled/disabled state of objects, allowing us to customize the Icinga Director config on one system without it being overwritten by an import.
* We are in contact with the Icinga guys about this project.
