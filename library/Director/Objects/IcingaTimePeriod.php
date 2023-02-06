<?php

namespace Icinga\Module\Director\Objects;

use Ramsey\Uuid\Uuid;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;

class IcingaTimePeriod extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_timeperiod';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'                => null,
        'uuid'              => null,
        'zone_id'           => null,
        'object_name'       => null,
        'object_type'       => null,
        'disabled'          => 'n',
        'prefer_includes'   => null,
        'display_name'      => null,
        'update_method'     => null,
    ];

    protected $booleans = [
        'prefer_includes'  => 'prefer_includes',
    ];

    protected $supportsImports = true;

    protected $supportsRanges = true;

    protected $supportedInLegacy = true;

    protected $relations = array(
        'zone' => 'IcingaZone',
    );

    protected $multiRelations = [
        'includes' => [
            'relatedObjectClass' => 'IcingaTimeperiod',
            'relatedShortName'   => 'include',
        ],
        'excludes' => [
            'relatedObjectClass' => 'IcingaTimeperiod',
            'relatedShortName'   => 'exclude',
            'legacyPropertyName' => 'exclude'
        ],
    ];

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    /**
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @return object
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        $props = (array) $this->toPlainObject();
        ksort($props);

        return (object) $props;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return static
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        $name = $properties['object_name'];
        $key = $name;

        // convert the string uuid to binary / an UuidInterface, which is how the rest of the code expects it to be
        $properties['uuid'] = Uuid::fromString($properties['uuid'])->getBytes();

        $table = 'icinga_timeperiod'; // since this is a static method we cannot use the class variable $table. redefine it here

        // check if there is an existing object in the database based on the uuid
        $dba = $db->getDbAdapter();
        $query = $dba->select()
            ->from($table)
            ->where('uuid = ?', $properties['uuid']);
        $candidates = self::loadAll($db, $query);
        if (count($candidates) == 1) {
            // by setting the key to the object with the uuid in the databse, the exists check succeeds, causing the old object to be loaded from the db and updated with the new values (setProperties() below).
            // note that this only works if $key is unique, since the initial load of the existing object is done via key instead of uuid with this method.
            // if that is a problem, we have to follow a similar approach as in v1.8.1.2021090901.
            $key = reset($candidates)->properties['object_name']; // reset() returns the first element of the array

        } elseif (count($candidates) > 1) {
            throw new DuplicateKeyException(
                'Time Period "%s" with uuid "%s" already exists. This means there is a duplicate uuid in the database. This should never happen.',
                $name,
                $properties['uuid']
            );
        }

        if ($replace && static::exists($key, $db)) {
            $object = static::load($key, $db);
        } elseif (static::exists($key, $db)) {
            throw new DuplicateKeyException(
                'Time Period "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }
        $object->setProperties($properties);

        return $object;
    }

    /**
     * Render update property
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderUpdate_method()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    protected function renderObjectHeader()
    {
        return parent::renderObjectHeader()
            . '    import "legacy-timeperiod"' . "\n";
    }

    protected function checkPeriodInRange($now, $name = null)
    {
        if ($name !== null) {
            $period = static::load($name, $this->connection);
        } else {
            $period = $this;
        }

        foreach ($period->ranges()->getRanges() as $range) {
            if ($range->isActive($now)) {
                return true;
            }
        }

        return false;
    }

    public function isActive($now = null)
    {
        if ($now === null) {
            $now = time();
        }

        $preferIncludes = $this->get('prefer_includes') !== 'n';

        $active = $this->checkPeriodInRange($now);
        $included = false;
        $excluded = false;

        $variants = [
            'includes' => &$included,
            'excludes' => &$excluded
        ];

        foreach ($variants as $key => &$var) {
            foreach ($this->get($key) as $name) {
                if ($this->checkPeriodInRange($now, $name)) {
                    $var = true;
                    break;
                }
            }
        }

        if ($preferIncludes) {
            if ($included) {
                return true;
            } elseif ($excluded) {
                return false;
            } else {
                return $active;
            }
        } else {
            if ($excluded) {
                return false;
            } elseif ($included) {
                return true;
            } else {
                return $active;
            }
        }

        // TODO: no range currently means (and renders) "never", Icinga behaves
        //       different. Figure out whether and how we should support this
        return false;
    }

    protected function prefersGlobalZone()
    {
        return true;
    }
}
