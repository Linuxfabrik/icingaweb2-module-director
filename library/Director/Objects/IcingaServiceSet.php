<?php

namespace Icinga\Module\Director\Objects;

use Exception;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\ServiceSetQueryBuilder;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Resolver\HostServiceBlacklist;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class IcingaServiceSet extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_service_set';

    protected $defaultProperties = array(
        'id'                    => null,
        'uuid'                  => null,
        'host_id'               => null,
        'object_name'           => null,
        'object_type'           => null,
        'description'           => null,
        'assign_filter'         => null,
    );

    protected $uuidColumn = 'uuid';

    protected $keyName = array('host_id', 'object_name');

    protected $supportsImports = true;

    protected $supportsCustomVars = true;

    protected $supportsApplyRules = true;

    protected $supportedInLegacy = true;

    protected $relations = array(
        'host' => 'IcingaHost',
    );

    public function isDisabled()
    {
        return false;
    }

    public function supportsAssignments()
    {
        return true;
    }

    protected function setKey($key)
    {
        if (is_int($key)) {
            $this->set('id', $key);
        } elseif (is_string($key)) {
            $keyComponents = preg_split('~!~', $key);
            if (count($keyComponents) === 1) {
                $this->set('object_name', $keyComponents[0]);
                $this->set('object_type', 'template');
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Can not parse key: %s',
                    $key
                ));
            }
        } else {
            return parent::setKey($key);
        }

        return $this;
    }

    /**
     * @return IcingaService[]
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getServiceObjects()
    {
        // don't try to resolve services for unstored objects - as in getServiceObjectsForSet()
        // also for diff in activity log
        if ($this->get('id') === null) {
            return [];
        }

        if ($this->get('host_id')) {
            $imports = $this->imports()->getObjects();
            if (empty($imports)) {
                return array();
            }
            $parent = array_shift($imports);
            assert($parent instanceof IcingaServiceSet);
            return $this->getServiceObjectsForSet($parent);
        } else {
            return $this->getServiceObjectsForSet($this);
        }
    }

    /**
     * @param IcingaServiceSet $set
     * @return IcingaService[]
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getServiceObjectsForSet(IcingaServiceSet $set)
    {
        $connection = $this->getConnection();
        if (self::$dbObjectStore !== null) {
            $branchUuid = self::$dbObjectStore->getBranch()->getUuid();
        } else {
            $branchUuid = null;
        }

        $builder = new ServiceSetQueryBuilder($connection, $branchUuid);
        return $builder->fetchServicesWithQuery($builder->selectServicesForSet($set));
    }

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    /**
     * @return object
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        if ($this->get('host_id')) {
            $result = $this->exportSetOnHost();
        } else {
            $result = $this->exportTemplate();
        }

        unset($result->uuid);
        return $result;
    }

    protected function exportSetOnHost()
    {
        // TODO.
        throw new RuntimeException('Not yet');
    }

    /**
     * @return object
     * @deprecated
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function exportTemplate()
    {
        $props = $this->getProperties();
        unset($props['id'], $props['host_id']);
        $props['services'] = [];
        foreach ($this->getServiceObjects() as $serviceObject) {
            $props['services'][$serviceObject->getObjectName()] = $serviceObject->export();
        }
        ksort($props);

        return (object) $props;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return IcingaServiceSet
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        $name = $properties['object_name'];
        if (isset($properties['services'])) {
            $services = $properties['services'];
            unset($properties['services']);
        } else {
            $services = [];
        }

        if ($properties['object_type'] !== 'template') {
            throw new InvalidArgumentException(sprintf(
                'Can import only Templates, got "%s" for "%s"',
                $properties['object_type'],
                $name
            ));
        }

        // convert the string uuid to binary / an UuidInterface, which is how the rest of the code expects it to be
        $properties['uuid'] = Uuid::fromString($properties['uuid'])->getBytes();

        $table = 'icinga_service_set'; // since this is a static method we cannot use the class variable $table. redefine it here

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
            $name = reset($candidates)->properties['object_name']; // reset() returns the first element of the array

        } elseif (count($candidates) > 1) {
            throw new DuplicateKeyException(
                'Service Template "%s" with uuid "%s" already exists. This means there is a duplicate uuid in the database. This should never happen.',
                $name,
                $properties['uuid']
            );
        }

        if ($replace && static::exists($name, $db)) {
            $object = static::load($name, $db);
        } elseif (static::exists($name, $db)) {
            throw new DuplicateKeyException(
                'Service Set "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }

        $object->setProperties($properties);

        // This is not how other imports work, but here we need an ID
        if (! $object->hasBeenLoadedFromDb()) {
            $object->store();
        }

        $setId = $object->get('id');
        $sQuery = $db->getDbAdapter()->select()->from(
            ['s' => 'icinga_service'],
            's.*'
        )->where('service_set_id = ?', $setId);
        $existingServices = IcingaService::loadAll($db, $sQuery, 'uuid');
        $serviceUuids = [];
        foreach ($services as $service) {
            // convert the string uuid to binary / an UuidInterface, which is how the rest of the code expects it to be
            $service->uuid = Uuid::fromString($service->uuid)->getBytes();

            if (isset($service->fields)) {
                unset($service->fields);
            }
            $uuid = $service->uuid;
            $serviceUuids[] = $uuid;
            if (isset($existingServices[$uuid])) {
                $existing = $existingServices[$uuid];
                $existing->setProperties((array) $service);
                $existing->set('service_set_id', $setId);
                if ($existing->hasBeenModified()) {
                    $existing->store();
                }
            } else {
                $new = IcingaService::create((array) $service, $db);
                $new->set('service_set_id', $setId);
                $new->store();
            }
        }

        foreach ($existingServices as $existing) {
            if (!in_array($existing->uuid, $serviceUuids)) {
                $existing->delete();
            }
        }

        return $object;
    }

    public function beforeDelete()
    {
        // check if this is a template, or directly assigned to a host
        if ($this->get('host_id') === null) {
            // find all host sets and delete them
            foreach ($this->fetchHostSets() as $set) {
                $set->delete();
            }
        }

        parent::beforeDelete();
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function onDelete()
    {
        $hostId = $this->get('host_id');
        if ($hostId) {
            $deleteIds = [];
            foreach ($this->getServiceObjects() as $service) {
                if ($idToDelete = $service->get('id')) {
                    $deleteIds[] = (int) $idToDelete;
                }
            }

            if (! empty($deleteIds)) {
                $db = $this->getDb();
                $db->delete(
                    'icinga_host_service_blacklist',
                    $db->quoteInto(
                        sprintf('host_id = %s AND service_id IN (?)', $hostId),
                        $deleteIds
                    )
                );
            }
        }

        parent::onDelete();
    }

    /**
     * @param IcingaConfig $config
     * @throws \Icinga\Exception\NotFoundError
     */
    public function renderToConfig(IcingaConfig $config)
    {
        // always print the header, so you have minimal info present
        $file = $this->getConfigFileWithHeader($config);

        if ($this->get('assign_filter') === null && $this->isTemplate()) {
            return;
        }

        if ($config->isLegacy()) {
            $this->renderToLegacyConfig($config);
            return;
        }

        $services = $this->getServiceObjects();
        if (empty($services)) {
            return;
        }

        // Loop over all services belonging to this set
        // add our assign rules and then add the service to the config
        // eventually clone them beforehand to not get into trouble with caches
        // figure out whether we might need a zone property
        foreach ($services as $service) {
            if ($filter = $this->get('assign_filter')) {
                $service->set('object_type', 'apply');
                $service->set('assign_filter', $filter);
            } elseif ($hostId = $this->get('host_id')) {
                $host = $this->getRelatedObject('host', $hostId)->getObjectName();
                if (in_array($host, $this->getBlacklistedHostnames($service))) {
                    continue;
                }
                $service->set('object_type', 'object');
                $service->set('use_var_overrides', 'y');
                $service->set('host_id', $hostId);
            } else {
                // Service set template without assign filter or host
                continue;
            }

            $this->copyVarsToService($service);
            $file->addObject($service);
        }
    }

    /**
     * @return array
     */
    public function getBlacklistedHostnames($service)
    {
        // Hint: if ($this->isApplyRule()) would be nice, but apply rules are
        // not enough, one might want to blacklist single services from Sets
        // assigned to single Hosts.
        if (PrefetchCache::shouldBeUsed()) {
            $lookup = PrefetchCache::instance()->hostServiceBlacklist();
        } else {
            $lookup = new HostServiceBlacklist($this->getConnection());
        }

        return $lookup->getBlacklistedHostnamesForService($service);
    }

    protected function getConfigFileWithHeader(IcingaConfig $config)
    {
        $file = $config->configFile(
            'zones.d/' . $this->getRenderingZone($config) . '/servicesets'
        );

        $file->addContent($this->getConfigHeaderComment($config));
        return $file;
    }

    protected function getConfigHeaderComment(IcingaConfig $config)
    {
        $name = $this->getObjectName();
        $assign = $this->get('assign_filter');

        if ($config->isLegacy()) {
            if ($assign !== null) {
                return "## applied Service Set '${name}'\n\n";
            } else {
                return "## Service Set '${name}' on this host\n\n";
            }
        } else {
            $comment = [
                "Service Set: ${name}",
            ];

            if (($host = $this->get('host')) !== null) {
                $comment[] = 'on host ' . $host;
            }

            if (($description = $this->get('description')) !== null) {
                $comment[] = '';
                foreach (preg_split('~\\n~', $description) as $line) {
                    $comment[] = $line;
                }
            }

            if ($assign !== null) {
                $comment[] = '';
                $comment[] = trim($this->renderAssign_Filter());
            }

            return "/**\n * " . join("\n * ", $comment) . "\n */\n\n";
        }
    }

    public function copyVarsToService(IcingaService $service)
    {
        $serviceVars = $service->vars();

        foreach ($this->vars() as $k => $var) {
            $serviceVars->$k = $var;
        }

        return $this;
    }

    /**
     * @param IcingaConfig $config
     * @throws \Icinga\Exception\NotFoundError
     */
    public function renderToLegacyConfig(IcingaConfig $config)
    {
        if ($this->get('assign_filter') === null && $this->isTemplate()) {
            return;
        }

        // evaluate my assign rules once, get related hosts
        // Loop over all services belonging to this set
        // generate every service with host_name host1,host2... -> not yet. And Zones?

        $conn = $this->getConnection();

        // Delegating this to the service would look, but this way it's faster
        if ($filter = $this->get('assign_filter')) {
            $filter = Filter::fromQueryString($filter);

            $hostnames = HostApplyMatches::forFilter($filter, $conn);
        } else {
            $hostnames = array($this->getRelated('host')->getObjectName());
        }

        $blacklists = [];

        foreach ($this->mapHostsToZones($hostnames) as $zone => $names) {
            $file = $config->configFile('director/' . $zone . '/servicesets', '.cfg');
            $file->addContent($this->getConfigHeaderComment($config));

            foreach ($this->getServiceObjects() as $service) {
                $object_name = $service->getObjectName();

                if (! array_key_exists($object_name, $blacklists)) {
                    $blacklists[$object_name] = $service->getBlacklistedHostnames();
                }

                // check if all hosts in the zone ignore this service
                $zoneNames = array_diff($names, $blacklists[$object_name]);

                $disabled = [];
                foreach ($zoneNames as $name) {
                    if (IcingaHost::load($name, $this->getConnection())->isDisabled()) {
                        $disabled[] = $name;
                    }
                }
                $zoneNames = array_diff($zoneNames, $disabled);

                if (empty($zoneNames)) {
                    continue;
                }

                $service->set('object_type', 'object');
                $service->set('host_id', $names);

                $this->copyVarsToService($service);

                $file->addLegacyObject($service);
            }
        }
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
        if ($this->get('host_id') === null) {
            if ($hostname = $this->get('host')) {
                $host = IcingaHost::load($hostname, $this->getConnection());
            } else {
                return $this->connection->getDefaultGlobalZoneName();
            }
        } else {
            $host = $this->getRelatedObject('host', $this->get('host_id'));
        }

        return $host->getRenderingZone($config);
    }

    public function createWhere()
    {
        $where = parent::createWhere();
        if (! $this->hasBeenLoadedFromDb()) {
            if (null === $this->get('host_id') && null === $this->get('id')) {
                $where .= " AND object_type = 'template'";
            }
        }

        return $where;
    }

    /**
     * @return IcingaService[]
     */
    public function fetchServices()
    {
        if ($store = self::$dbObjectStore) {
            $uuid = $store->getBranch()->getUuid();
        } else {
            $uuid = null;
        }
        $builder = new ServiceSetQueryBuilder($this->getConnection(), $uuid);
        return $builder->fetchServicesWithQuery($builder->selectServicesForSet($this));
    }

    /**
     * Fetch IcingaServiceSet that are based on this set and added to hosts directly
     *
     * @return IcingaServiceSet[]
     */
    public function fetchHostSets()
    {
        $id = $this->get('id');
        if ($id === null) {
            return [];
        }

        $query = $this->db->select()
            ->from(
                ['o' => $this->table]
            )->join(
                ['ssi' => $this->table . '_inheritance'],
                'ssi.service_set_id = o.id',
                []
            )->where(
                'ssi.parent_service_set_id = ?',
                $id
            );

        return static::loadAll($this->connection, $query);
    }

    /**
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function beforeStore()
    {
        parent::beforeStore();

        $name = $this->getObjectName();

        if ($this->isObject() && $this->get('host_id') === null && $this->get('host') === null) {
            throw new InvalidArgumentException(
                'A Service Set cannot be an object with no related host'
            );
        }
        // checking if template object_name is unique
        // TODO: Move to IcingaObject
        if (! $this->hasBeenLoadedFromDb() && $this->isTemplate() && static::exists($name, $this->connection)) {
            throw new DuplicateKeyException(
                '%s template "%s" already existing in database!',
                $this->getType(),
                $name
            );
        }
    }

    public function toSingleIcingaConfig()
    {
        $config = parent::toSingleIcingaConfig();

        try {
            foreach ($this->fetchHostSets() as $set) {
                $set->renderToConfig($config);
            }
        } catch (Exception $e) {
            $config->configFile(
                'failed-to-render'
            )->prepend(
                "/** Failed to render this Service Set **/\n"
                . '/*  ' . $e->getMessage() . ' */'
            );
        }

        return $config;
    }
}
