<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use InvalidArgumentException;
use Zend_Form_Element as ZfElement;

class DirectorDatafield extends DbObjectWithSettings
{
    protected $table = 'director_datafield';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'            => null,
        'varname'       => null,
        'caption'       => null,
        'description'   => null,
        'datatype'      => null,
        'format'        => null,
        'guid'          => null,
    );

    protected $settingsTable = 'director_datafield_setting';

    protected $settingsRemoteId = 'datafield_id';

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

    protected $shouldBeRenamed = false;
    protected $oldName = '';

    public function shouldBeRenamed() {
        return $this->shouldBeRenamed;
    }

    public function getOldName() {
        return $this->oldName;
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

        if (property_exists($plain->settings, 'datalist_id')) {
            $plain->settings->datalist = DirectorDatalist::loadWithAutoIncId(
                $plain->settings->datalist_id,
                $this->getConnection()
            )->get('list_name');
            unset($plain->settings->datalist_id);
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
        $properties = (array) $plain;
        $encoded = Json::encode($properties);

        if (isset($properties['settings']->datalist)) {
            // Just try to load the list, import should fail if missing
            $list = DirectorDatalist::load(
                $properties['settings']->datalist,
                $db
            );
        } else {
            $list = null;
        }

        if ($list) {
            unset($properties['settings']->datalist);
            $properties['settings']->datalist_id = $list->get('id');
        }

        if (isset($properties['guid'])) {
            // check if there is an entry in the database with the same guid
            $dba = $db->getDbAdapter();
            $query = $dba->select()
                ->from('director_datafield')
                ->where('guid = ?', $properties['guid']);
            $candidates = self::loadAll($db, $query);
            if (count($candidates) == 1) {
                $candidate = reset($candidates);
                $export = $candidate->export();
                $export_id = $export->originalId;
                unset($export->originalId);
                if (Json::encode($export) === $encoded) {
                    // if the entry is same as the new object, do nothing
                    return $candidate;
                } else {
                    $properties['id'] = $export_id; // set id, as this is used in the WHERE clause of the update later on
                    $obj = static::create($properties, $db);
                    $obj->hasBeenModified = true; // a modified object will updated later on
                    $obj->loadedFromDb = true; // use update instead of insert (DbObject store())
                    if ($export->varname != $properties['varname']) {
                        $obj->shouldBeRenamed = true;
                        $obj->oldName = $export->varname;
                    }
                    return $obj;
                }
            } elseif (count($candidates) == 0) {
                // the object to be imported has a guid, but is not found in the databse. this means, it has to be a new object.
                unset($properties['originalId']);
                return static::create($properties, $db);
            } elseif (count($candidates) > 1) {
                throw new DuplicateKeyException(
                    'Datafield "%s" with guid "%s" already exists. This means there is a duplicate guid in the database. This should never happen.',
                    $name,
                    $properties['guid']
                );
            }
        }

        if (isset($properties['originalId'])) {
            $id = $properties['originalId'];
            unset($properties['originalId']);
        } else {
            $id = null;
        }

        if ($id) {
            if (static::exists($id, $db)) {
                $existing = static::loadWithAutoIncId($id, $db);
                $existingProperties = (array) $existing->export();
                unset($existingProperties['originalId']);
                if ($encoded === Json::encode($existingProperties)) {
                    return $existing;
                }
            }
        }

        $dba = $db->getDbAdapter();
        $query = $dba->select()
            ->from('director_datafield')
            ->where('varname = ?', $plain->varname);
        $candidates = DirectorDatafield::loadAll($db, $query);

        foreach ($candidates as $candidate) {
            $export = $candidate->export();
            unset($export->originalId);
            if (Json::encode($export) === $encoded) {
                return $candidate;
            }
        }

        return static::create($properties, $db);
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
        if ($object instanceof IcingaObject) {
            if ($object->isTemplate()) {
                $el->setRequired(false);
            }

            $varname = $this->get('varname');

            $inherited = $object->getInheritedVar($varname);

            if (null !== $inherited) {
                $form->setInheritedValue(
                    $el,
                    $inherited,
                    $object->getOriginForVar($varname)
                );
            } elseif ($object->hasRelation('check_command')) {
                // TODO: Move all of this elsewhere and test it
                try {
                    /** @var IcingaCommand $command */
                    $command = $object->getResolvedRelated('check_command');
                    if ($command === null) {
                        return;
                    }
                    $inherited = $command->vars()->get($varname);
                    $inheritedFrom = null;

                    if ($inherited !== null) {
                        $inherited = $inherited->getValue();
                    }

                    if ($inherited === null) {
                        $inherited = $command->getResolvedVar($varname);
                        if ($inherited === null) {
                            $inheritedFrom = $command->getOriginForVar($varname);
                        }
                    } else {
                        $inheritedFrom = $command->getObjectName();
                    }

                    $inherited = $command->getResolvedVar($varname);
                    if (null !== $inherited) {
                        $form->setInheritedValue(
                            $el,
                            $inherited,
                            $inheritedFrom
                        );
                    }
                } catch (\Exception $e) {
                    // Ignore failures
                }
            }
        }
    }
}
