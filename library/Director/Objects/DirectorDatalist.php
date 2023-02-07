<?php

namespace Icinga\Module\Director\Objects;

use Exception;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Ramsey\Uuid\Uuid;

class DirectorDatalist extends DbObject implements ExportInterface
{
    protected $table = 'director_datalist';

    protected $uuidColumn = 'uuid';

    protected $keyName = 'list_name';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'            => null,
        'uuid'          => null,
        'list_name'     => null,
        'owner'         => null
    );

    /** @var DirectorDatalistEntry[] */
    protected $storedEntries;

    public function getUniqueIdentifier()
    {
        return $this->get('list_name');
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return static
     * @throws \Icinga\Exception\NotFoundError
     * @throws DuplicateKeyException
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        if (isset($properties['originalId'])) {
            unset($properties['originalId']);
        } else {
            $id = null;
        }
        $name = $properties['list_name'];

        // convert the string uuid to binary / an UuidInterface, which is how the rest of the code expects it to be
        $properties['uuid'] = Uuid::fromString($properties['uuid'])->getBytes();

        $table = 'director_datalist'; // since this is a static method we cannot use the class variable $table. redefine it here

        // check if there is an existing object in the database based on the uuid
        $dba = $db->getDbAdapter();
        $query = $dba->select()
            ->from($table)
            ->where('uuid = ?', $properties['uuid']);
        $candidates = self::loadAll($db, $query);
        if (count($candidates) == 1) {
            // by setting the name to the object with the uuid in the databse, the exists check succeeds, causing the old object to be loaded from the db and updated with the new values (setProperties() below).
            // note that this only works if $name is unique, since the initial load of the existing object is done via name instead of uuid with this method.
            // if that is a problem, we have to follow a similar approach as in v1.8.1.2021090901.
            $name = reset($candidates)->properties['list_name']; // reset() returns the first element of the array

        } elseif (count($candidates) > 1) {
            throw new DuplicateKeyException(
                'Data List "%s" with uuid "%s" already exists. This means there is a duplicate uuid in the database. This should never happen.',
                $name,
                $properties['uuid']
            );
        }

        if ($replace && static::exists($name, $db)) {
            $object = static::load($name, $db);
        } elseif (static::exists($name, $db)) {
            throw new DuplicateKeyException(
                'Data List %s already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }
        $object->setProperties($properties);

        return $object;
    }

    public function setEntries($entries)
    {
        $existing = $this->getStoredEntries();

        $new = [];
        $seen = [];
        $modified = false;

        foreach ($entries as $entry) {
            $name = $entry->entry_name;
            $entry = DirectorDatalistEntry::create((array) $entry);
            $seen[$name] = true;
            if (isset($existing[$name])) {
                $existing[$name]->replaceWith($entry);
                if (! $modified && $existing[$name]->hasBeenModified()) {
                    $modified = true;
                }
            } else {
                $modified = true;
                $new[] = $entry;
            }
        }

        foreach (array_keys($existing) as $key) {
            if (! isset($seen[$key])) {
                $existing[$key]->markForRemoval();
                $modified = true;
            }
        }

        foreach ($new as $entry) {
            $existing[$entry->get('entry_name')] = $entry;
        }

        if ($modified) {
            $this->hasBeenModified = true;
        }

        $this->storedEntries = $existing;
        ksort($this->storedEntries);

        return $this;
    }

    protected function beforeDelete()
    {
        if ($this->hasBeenUsed()) {
            throw new Exception(
                sprintf(
                    "Cannot delete '%s', as the datalist '%s' is currently being used.",
                    $this->get('list_name'),
                    $this->get('list_name')
                )
            );
        }
    }

    protected function hasBeenUsed()
    {
        $datalistType = 'Icinga\\Module\\Director\\DataType\\DataTypeDatalist';
        $db = $this->getDb();

        $dataFieldsCheck = $db->select()
            ->from(['df' =>'director_datafield'], ['varname'])
            ->join(
                ['dfs' => 'director_datafield_setting'],
                'dfs.datafield_id = df.id AND dfs.setting_name = \'datalist_id\'',
                []
            )
            ->join(
                ['l' => 'director_datalist'],
                'l.id = dfs.setting_value',
                []
            )
            ->where('datatype = ?', $datalistType)
            ->where('setting_value = ?', $this->get('id'));

        if ($db->fetchOne($dataFieldsCheck)) {
            return true;
        }

        $syncCheck = $db->select()
            ->from(['sp' =>'sync_property'], ['source_expression'])
            ->where('sp.destination_field = ?', 'list_id')
            ->where('sp.source_expression = ?', $this->get('id'));

        if ($db->fetchOne($syncCheck)) {
            return true;
        }

        return false;
    }

    /**
     * @throws DuplicateKeyException
     */
    public function onStore()
    {
        if ($this->storedEntries) {
            $db = $this->getConnection();
            $removedKeys = [];
            $myId = $this->get('id');

            foreach ($this->storedEntries as $key => $entry) {
                if ($entry->shouldBeRemoved()) {
                    $entry->delete();
                    $removedKeys[] = $key;
                } else {
                    if (! $entry->hasBeenLoadedFromDb()) {
                        $entry->set('list_id', $myId);
                    }
                    $entry->set('list_id', $myId);
                    $entry->store($db);
                }
            }

            foreach ($removedKeys as $key) {
                unset($this->storedEntries[$key]);
            }
        }
    }

    /**
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @return object
     */
    public function export()
    {
        $plain = (object) $this->getProperties();
        $plain->originalId = $plain->id;
        unset($plain->id);

        $plain->entries = [];
        foreach ($this->getStoredEntries() as $key => $entry) {
            if ($entry->shouldBeRemoved()) {
                continue;
            }
            $plainEntry = (object) $entry->getProperties();
            unset($plainEntry->list_id);

            $plain->entries[] = $plainEntry;
        }

        return $plain;
    }

    protected function getStoredEntries()
    {
        if ($this->storedEntries === null) {
            if ($id = $this->get('id')) {
                $this->storedEntries = DirectorDatalistEntry::loadAllForList($this);
                ksort($this->storedEntries);
            } else {
                $this->storedEntries = [];
            }
        }

        return $this->storedEntries;
    }
}
