# Manual Testing of the Fork Features

Since the phpunit tests currently do not seem to work with the Uuid library, we manually test the newly added features of the fork.

| Object                 | Test 1 | Test 2 | Test 3 | Test 4 | Test 5 | Test 6 | Test 7 |
| ---                    | ---    | ---    | ---    | ---    | ---    | ---    | ---    |
| Commands               | PASS   | PASS   | PASS   | PASS   | N/A    | N/A    | N/A    |
| Service Templates      | PASS   | PASS   | PASS   | PASS   | N/A    | N/A    | N/A    |
| Notification Templates | PASS   | PASS   | PASS   | PASS   | N/A    | N/A    | N/A    |
| Host Templates         | PASS   | PASS   | PASS   | PASS   | N/A    | N/A    | N/A    |
| Dependencies           | PASS   | PASS   | PASS   | PASS   | N/A    | N/A    | N/A    |
| Timeperiods            | PASS   | PASS   | PASS   | PASS   | N/A    | N/A    | N/A    |
| Service Sets           | todo   | todo   | todo   | todo   | N/A    | N/A    | todo   |
| DataFields             | todo   | todo   | todo   | todo   | todo   | todo   | todo   |
| DataLists              | todo   | todo   | todo   | todo   | todo   | todo   | todo   |


## Preparations
Do this once before running the other tests.

```bash
icingacli director basket restore << 'EOF'
{
    "Basket": {
        "export": {
            "basket_name": "export",
                "objects": {
                    "Command": true,
                    "HostTemplate": true,
                    "ServiceTemplate": true,
                    "ServiceSet": true,
                    "Notification": true,
                    "NotificationTemplate": true,
                    "TimePeriod": true,
                    "Dependency": true,
                    "DataList": true
                },
            "owner_type": "user",
            "owner_value": "admin"
        }
    }
}
EOF
```


## Test 1: export with uuids
* create object via the webgui
* `icingacli director basket dump --name export`
* make sure that a uuid is present and not null


## Test 2: import with uuids
* `icingacli director basket restore --purge <Object> < /usr/share/icingaweb2/modules/director/test/php/library/Director/Objects/json/<object>1.json`
* make sure that the uuid in the database matches the one in the file (`SELECT id, object_name, HEX(uuid) FROM icinga_director.icinga_<object> WHERE object_name LIKE '___TEST___%';`)


## Test 3: change name during import
* import the initial object:
    * `icingacli director basket restore --purge <Object> < /usr/share/icingaweb2/modules/director/test/php/library/Director/Objects/json/<object>1.json`
    * `icingacli director basket dump --name export`
* import the renamed object:
    * `icingacli director basket restore < /usr/share/icingaweb2/modules/director/test/php/library/Director/Objects/json/<object>1-renamed.json`
    * `icingacli director basket dump --name export`
* make sure that there is only one object with the new name and same uuid present


## Test 4: cloning with uuids
* `icingacli director basket restore --purge <Object> < /usr/share/icingaweb2/modules/director/test/php/library/Director/Objects/json/<object>1.json`
* `icingacli director basket dump --name export`
* clone the object via the webgui (the cli does not work for all object types)
* `icingacli director basket dump --name export`
* make sure that the clone has a different uuid


## Test 5: renaming a datafield
* todo
* applied custom variables (for example on a host) should be renamed as well


## Test 6: remove entry from datalist
* todo
* entry should be absent in the director


## Test 7: remove service from ServiceSet
* todo
* service should be absent from the ServiceSet
