<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\CompareBasketObject;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Resolver\OverriddenVarsResolver;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Zend_Form_Element as ZfElement;

class DirectorDatafield extends DbObjectWithSettings
{
    protected $table = 'director_datafield';

    protected $uuidColumn = 'uuid';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'category_id'   => null,
        'varname'       => null,
        'caption'       => null,
        'description'   => null,
        'datatype'      => null,
        'format'        => null,
    ];

    protected $relations = [
        'category'      => 'DirectorDatafieldCategory'
    ];

    protected $settingsTable = 'director_datafield_setting';

    protected $settingsRemoteId = 'datafield_id';

    protected $shouldBeRenamed = false;
    protected $preImportName = '';

    public function shouldBeRenamed() {
        return $this->shouldBeRenamed;
    }

    public function getPreImportName() {
        return $this->preImportName;
    }

    /** @var DirectorDatafieldCategory|null */
    private $category;

    private $object;

    public static function fromDbRow($row, Db $connection)
    {
        $obj = static::create((array) $row, $connection);
        $obj->loadedFromDb = true;
        // TODO: $obj->setUnmodified();
        $obj->hasBeenModified = false;
        $obj->modifiedProperties = array();
        $settings = $obj->getSettings();
        // TODO: eventually prefetch
        $obj->onLoadFromDb();

        // Restoring values eventually destroyed by onLoadFromDb
        foreach ($settings as $key => $value) {
            $obj->settings[$key] = $value;
        }

        return $obj;
    }

    public function hasCategory()
    {
        return $this->category !== null || $this->get('category_id') !== null;
    }

    /**
     * @return DirectorDatafieldCategory|null
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getCategory()
    {
        if ($this->category) {
            return $this->category;
        } elseif ($id = $this->get('category_id')) {
            return DirectorDatafieldCategory::loadWithAutoIncId($id, $this->getConnection());
        } else {
            return null;
        }
    }

    public function getCategoryName()
    {
        $category = $this->getCategory();
        if ($category === null) {
            return null;
        } else {
            return $category->get('category_name');
        }
    }

    public function setCategory($category)
    {
        if ($category === null) {
            $this->category = null;
            $this->set('category_id', null);
        } elseif ($category instanceof DirectorDatafieldCategory) {
            if ($category->hasBeenLoadedFromDb()) {
                $this->set('category_id', $category->get('id'));
            }
            $this->category = $category;
        } else {
            if (DirectorDatafieldCategory::exists($category, $this->getConnection())) {
                $this->setCategory(DirectorDatafieldCategory::load($category, $this->getConnection()));
            } else {
                $this->setCategory(DirectorDatafieldCategory::create([
                    'category_name' => $category
                ], $this->getConnection()));
            }
        }

        return $this;
    }

    /**
     * @return object
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        $plain = (object) $this->getProperties();
        $plain->originalId = $plain->id;
        unset($plain->id);
        $plain->settings = (object) $this->getSettings();

        // doing this here instead of the Exporter class since apparently DataFields do not use the Exporter class
        if ($this->hasUuidColumn()) {
            // augment output with uuid if present
            $plain->uuid = Uuid::fromBytes($plain->uuid)->toString();
        }

        if (property_exists($plain->settings, 'datalist_id')) {
            $plain->settings->datalist = DirectorDatalist::loadWithAutoIncId(
                $plain->settings->datalist_id,
                $this->getConnection()
            )->get('list_name');
            unset($plain->settings->datalist_id);
        }
        if (property_exists($plain, 'category_id')) {
            $plain->category = $this->getCategoryName();
            unset($plain->category_id);
        }

        return $plain;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return DirectorDatafield
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        // since this function is weird (see comments at end of function), we implement a completely different logic:
        // try loading an existing object via the uuids
        // if there is none, create a new object
        // if there is one and they are the same, return the existing one
        // if there is one and they differ, update the existing one with the new properties manually and return the modified object

        $properties = (array) $plain;
        // we don't care about the originalId
        if (isset($properties['originalId'])) {
            unset($properties['originalId']);
        }

        // get the matching list via the key (there is no uuid relationship here)
        if (isset($properties['settings']->datalist)) {
            // Just try to load the list, import should fail if missing
            $list = DirectorDatalist::load(
                $properties['settings']->datalist,
                $db
            );
            // and directly set it
            unset($properties['settings']->datalist);
            $properties['settings']->datalist_id = $list->get('id');
        }

        $compare = Json::decode(Json::encode($properties));

        // convert the string uuid to binary / an UuidInterface, which is how the rest of the code expects it to be
        // but after the encode to json, since that would lead to an error
        $properties['uuid'] = Uuid::fromString($properties['uuid'])->getBytes();

        $table = 'director_datafield'; // since this is a static method we cannot use the class variable $table. redefine it here

        // check if there is an existing object in the database based on the uuid
        $dba = $db->getDbAdapter();
        $query = $dba->select()
            ->from($table)
            ->where('uuid = ?', $properties['uuid']);
        $candidates = self::loadAll($db, $query);
        if (count($candidates) == 1) {
            $candidate = reset($candidates);
            $export = $candidate->export();
            // we need to keep onto the id in case we want to update this (existing) object
            $export_id = $export->originalId;
            unset($export->originalId);
            CompareBasketObject::normalize($export);
            if (CompareBasketObject::equals($export, $compare)) {
                // if the entry is same as the new object, we are done here
                return $candidate;
            } else {
                $properties['id'] = $export_id; // set id, as this is used in the WHERE clause of the update later on
                $obj = static::create($properties, $db);
                $obj->hasBeenModified = true; // setting this forces the object to be stored in the db later on
                $obj->loadedFromDb = true; // setting this leads to the use of UPDATE instead of INSERT when storing it in the db (in DbObject store())
                // additionally: setting the new shouldBeRenamed flag leads to all associated custom variables to be renamed later on (see BasketSnapshotFieldResolver->storeNewFields())
                if ($export['varname'] != $properties['varname']) {
                    $obj->shouldBeRenamed = true;
                    $obj->preImportName = $export['varname'];
                }
                return $obj;
            }
        } elseif (count($candidates) > 1) {
            throw new DuplicateKeyException(
                'Data Field "%s" with uuid "%s" already exists. This means there is a duplicate uuid in the database. This should never happen.',
                $name,
                $properties['uuid']
            );
        }

        // original code:
        // trying to find an existing object using the originalId, this will fail in 99% of the use cases, since the object with that id will be different and therefore CompareBasketObject::equals() will return false.
        // even when setting the correct existing id using uuids, the CompareBasketObject::equals() will return false if there are any changes
        // $compare = Json::decode(Json::encode($properties));
        // if ($id && static::exists($id, $db)) {
        //     $existing = static::loadWithAutoIncId($id, $db);
        //     $existingProperties = (array) $existing->export();
        //     unset($existingProperties['originalId']);
        //     if (CompareBasketObject::equals((object) $compare, (object) $existingProperties)) {
        //         return $existing;
        //     }
        // }
        //
        // // make sure the datalist_id is correct. not sure why this is not done before comparing with the originalId candidate
        // if ($list) {
        //     unset($properties['settings']->datalist);
        //     $properties['settings']->datalist_id = $list->get('id');
        // }
        //
        // // next attempt of finding an existing object by searching for the varname, and then comparing. this will fail if any property differs
        // $dba = $db->getDbAdapter();
        // $query = $dba->select()
        //     ->from('director_datafield')
        //     ->where('varname = ?', $plain->varname);
        // $candidates = DirectorDatafield::loadAll($db, $query);
        //
        // foreach ($candidates as $candidate) {
        //     $export = $candidate->export();
        //     unset($export->originalId);
        //     CompareBasketObject::normalize($export);
        //     if (CompareBasketObject::equals($export, $compare)) {
        //         return $candidate;
        //     }
        // }

        // most likely outcome (without uuid), creating a new datafield
        return static::create($properties, $db);

        // why is the candidate found via originalId treated differently than the candidates found by varname? why is the list id only fixed after the candidate via originalId?
        // alternate suggestion: create a list of candidates via originalId and varname (originalId pos 0, rest afterwards), iterate that list and consistenly do the same equals check
    }

    protected function beforeStore()
    {
        if ($this->category) {
            if (!$this->category->hasBeenLoadedFromDb()) {
                throw new \RuntimeException('Trying to store a datafield with an unstored Category');
            }
            $this->set('category_id', $this->category->get('id'));
        }
    }

    protected function setObject(IcingaObject $object)
    {
        $this->object = $object;
    }

    protected function getObject()
    {
        return $this->object;
    }

    public function getFormElement(DirectorObjectForm $form, $name = null)
    {
        $className = $this->get('datatype');

        if ($name === null) {
            $name = 'var_' . $this->get('varname');
        }

        if (! class_exists($className)) {
            $form->addElement('text', $name, array('disabled' => 'disabled'));
            $el = $form->getElement($name);
            $msg = $form->translate('Form element could not be created, %s is missing');
            $el->addError(sprintf($msg, $className));
            return $el;
        }

        /** @var DataTypeHook $dataType */
        $dataType = new $className;
        $dataType->setSettings($this->getSettings());
        $el = $dataType->getFormElement($name, $form);

        if ($this->getSetting('icinga_type') !== 'command'
            && $this->getSetting('is_required') === 'y'
        ) {
            $el->setRequired(true);
        }
        if ($caption = $this->get('caption')) {
            $el->setLabel($caption);
        }

        if ($description = $this->get('description')) {
            $el->setDescription($description);
        }

        $this->applyObjectData($el, $form);

        return $el;
    }

    protected function applyObjectData(ZfElement $el, DirectorObjectForm $form)
    {
        $object = $form->getObject();
        if (! ($object instanceof IcingaObject)) {
            return;
        }
        if ($object->isTemplate()) {
            $el->setRequired(false);
        }

        $varName = $this->get('varname');
        $inherited = $origin = null;

        if ($form instanceof IcingaServiceForm && $form->providesOverrides()) {
            $resolver = new OverriddenVarsResolver($form->getDb());
            $vars = $resolver->fetchForServiceName($form->getHost(), $object->getObjectName());
            foreach ($vars as $host => $values) {
                if (\property_exists($values, $varName)) {
                    $inherited = $values->$varName;
                    $origin = $host;
                }
            }
        }

        if ($inherited === null) {
            $inherited = $object->getInheritedVar($varName);
            if (null !== $inherited) {
                $origin = $object->getOriginForVar($varName);
            }
        }

        if ($inherited === null) {
            $cmd = $this->eventuallyGetResolvedCommandVar($object, $varName);
            if ($cmd !== null) {
                list($inherited, $origin) = $cmd;
            }
        }

        if ($inherited !== null) {
            $form->setInheritedValue($el, $inherited, $origin);
        }
    }

    protected function eventuallyGetResolvedCommandVar(IcingaObject $object, $varName)
    {
        if (! $object->hasRelation('check_command')) {
            return null;
        }

        // TODO: Move all of this elsewhere and test it
        try {
            /** @var IcingaCommand $command */
            $command = $object->getResolvedRelated('check_command');
            if ($command === null) {
                return null;
            }
            $inherited = $command->vars()->get($varName);
            $inheritedFrom = null;

            if ($inherited !== null) {
                $inherited = $inherited->getValue();
            }

            if ($inherited === null) {
                $inherited = $command->getResolvedVar($varName);
                if ($inherited === null) {
                    $inheritedFrom = $command->getOriginForVar($varName);
                }
            } else {
                $inheritedFrom = $command->getObjectName();
            }

            $inherited = $command->getResolvedVar($varName);

            return [$inherited, $inheritedFrom];
        } catch (\Exception $e) {
            return null;
        }
    }
}
