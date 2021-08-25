# Linuxfabrik Fork of the Icinga Director

## Motivation - Why we forked

We are managing the Icinga Director configuration of many Icinga2 Servers using the Icinga Director Basket Ex- and Import. Currently the basket matches objects using its _name_, which means that it is _impossible_ to rename existing objects. Instead, if renaming, a new object is created with the new name, requiring us to manually delete the object with the old name on every Icinga2 Server.

This is especially problematic with DataFields: If you change any attribute (for example the caption or just the description) the original Icinga Director Basket creates a _new_ DataField. Assuming one did not modify the field name, we now have two DataFields with the same field name. When deleting the first/old one, you will be prompted if the related vars should be wiped.

This behavior is ugly and misleading, and the problem simply exists because the Icinga Director is not relying on IDs in its database.

We changed that.


## Features

This fork of the Icinga Director implements [GUIDs](https://en.wikipedia.org/wiki/Universally_unique_identifier) for most of the database objects to make the handling of the director basket easier.

The following objects will be saved with a GUID in the database when they are created or imported from a basket:

* DataFields
* Commands
* Service Templates
* Service Sets
* Host Templates
* Notification Templates
* Timeperiods
* Dependencies
* DataLists

This allows the following tasks to be accomplished by importing a basket:

* Changing the name of objects listed above. Without GUIDs, this would always create a new object instead.
* DataFields: When renaming, all applied custom variables (for example to a host) will be renamed as well.
* DataLists: Entries can be removed from the list. Note: This does not affect applied entries, as they are saved as strings in the database. See [Known Limitations](#known-limitations) below.
* Service Sets: Services can be removed from the set.


## Installation

The module needs modifications to the director MySQL/MariaDB database (provided in the `schema/guid.sql` file).
You can import the schema using the following command:
```bash
mysql -p -u root director < schema/guid.sql
```

Please follow the [original installation guide](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director/-/blob/v1.7.2.2020111901/doc/02-Installation.md) but use our [latest release](https://git.linuxfabrik.ch/linuxfabrik/icingaweb2-module-director/-/releases) instead of the original director.


## Known limitations

* DataFields: Renaming or removing an entry will only rename/remove the entry in the datalist, not the applied variables on other objects such as hosts or services.
* Cloning does not work right now, because it tries to duplicate the GUID.


## Future

* The GUIDs could be used to allow deletion of certain objects during the basket import, for example a deprecated service.
* The basket import could ignore the enabled/disabled state of objects, allowing us to customize the Icinga Director config on one system without it being overwritten by an import.
* We are in contact with the Icinga guys about this project.
