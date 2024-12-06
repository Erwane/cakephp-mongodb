<?php
declare(strict_types=1);

namespace CakeMongo\ODM;

use ArrayObject;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Exception\CakeException;
use Cake\Database\Connection;
use Cake\Database\Exception\DatabaseException;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Database\TypeFactory;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\InvalidPrimaryKeyException;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\RulesAwareTrait;
use Cake\Datasource\RulesChecker;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\ORM\Exception\MissingEntityException;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Rule\IsUnique;
use Cake\Utility\Inflector;
use Cake\Validation\ValidatorAwareInterface;
use Cake\Validation\ValidatorAwareTrait;
use CakeMongo\Database\MongoConnection;
use CakeMongo\ODM\Query\InsertQuery;
use CakeMongo\ODM\Query\QueryFactory;
use CakeMongo\ODM\Query\SelectQuery;
use Closure;
use Psr\SimpleCache\CacheInterface;

class Collection implements RepositoryInterface, EventListenerInterface, EventDispatcherInterface, ValidatorAwareInterface
{
    use EventDispatcherTrait;
    use RulesAwareTrait;
    use ValidatorAwareTrait;

    protected QueryFactory $queryFactory;

    /**
     * Name of default validation set.
     *
     * @var string
     */
    public const DEFAULT_VALIDATOR = 'default';

    /**
     * The alias this object is assigned to validators as.
     *
     * @var string
     */
    public const VALIDATOR_PROVIDER_NAME = 'table';

    /**
     * The name of the event dispatched when a validator has been built.
     *
     * @var string
     */
    public const BUILD_VALIDATOR_EVENT = 'Model.buildValidator';

    /**
     * The rules class name that is used.
     *
     * @var class-string<\Cake\ORM\RulesChecker>
     */
    public const RULES_CLASS = RulesChecker::class;

    /**
     * The IsUnique class name that is used.
     *
     * @var class-string<\Cake\ORM\Rule\IsUnique>
     */
    public const IS_UNIQUE_CLASS = IsUnique::class;

    /**
     * Name of the table as it can be found in the database
     *
     * @var string|null
     */
    protected ?string $_table = null;

    /**
     * Human name giving to this particular instance. Multiple objects representing
     * the same database table can exist by using different aliases.
     *
     * @var string|null
     */
    protected ?string $_alias = null;

    /**
     * Connection instance
     *
     * @var \CakeMongo\Database\MongoConnection|null
     */
    protected ?MongoConnection $_connection = null;

    /**
     * The schema object containing a description of this table fields
     *
     * @var \Cake\Database\Schema\TableSchemaInterface|null
     */
    protected ?TableSchemaInterface $_schema = null;

    /**
     * The name of the field that represents the primary key in the table
     *
     * @var list<string>|string|null
     */
    protected array|string|null $_primaryKey = null;

    /**
     * The name of the field that represents a human-readable representation of a row
     *
     * @var list<string>|string|null
     */
    protected array|string|null $_displayField = null;

    /**
     * @var \Cake\ORM\BehaviorRegistry
     */
    // protected BehaviorRegistry $_behaviors;

    /**
     * The name of the class that represent a single row for this table
     *
     * @var string|null
     * @psalm-var class-string<\Cake\Datasource\EntityInterface>|null
     */
    protected ?string $_entityClass = null;

    /**
     * Registry key used to create this table object
     *
     * @var string|null
     */
    protected ?string $_registryAlias = null;

    public function __construct(array $config = [])
    {
        if (!empty($config['registryAlias'])) {
            $this->setRegistryAlias($config['registryAlias']);
        }
        if (!empty($config['table'])) {
            $this->setTable($config['table']);
        }
        if (!empty($config['alias'])) {
            $this->setAlias($config['alias']);
        }
        if (!empty($config['connection'])) {
            $this->setConnection($config['connection']);
        }
        if (!empty($config['queryFactory'])) {
            $this->queryFactory = $config['queryFactory'];
        }
        if (!empty($config['schema'])) {
            $this->setSchema($config['schema']);
        }
        if (!empty($config['entityClass'])) {
            $this->setEntityClass($config['entityClass']);
        }
        $eventManager = null;
        // $behaviors = null;
        // $associations = null;
        if (!empty($config['eventManager'])) {
            $eventManager = $config['eventManager'];
        }
        // if (!empty($config['behaviors'])) {
        //     $behaviors = $config['behaviors'];
        // }
        // if (!empty($config['associations'])) {
        //     $associations = $config['associations'];
        // }
        if (!empty($config['validator'])) {
            if (!is_array($config['validator'])) {
                $this->setValidator(static::DEFAULT_VALIDATOR, $config['validator']);
            } else {
                foreach ($config['validator'] as $name => $validator) {
                    $this->setValidator($name, $validator);
                }
            }
        }
        $this->_eventManager = $eventManager ?: new EventManager();
        /** @var \Cake\ORM\BehaviorRegistry $behaviors */
        // $this->_behaviors = $behaviors ?: new BehaviorRegistry();
        // $this->_behaviors->setTable($this);

        /** @psalm-suppress TypeDoesNotContainType */
        $this->queryFactory ??= new QueryFactory();

        $this->initialize($config);

        assert($this->_eventManager !== null, 'EventManager not available');

        $this->_eventManager->on($this);
        $this->dispatchEvent('Model.initialize');
    }

    /**
     * Get the default connection name.
     * This method is used to get the fallback connection name if an
     * instance is created through the TableLocator without a connection.
     *
     * @return string
     * @see \Cake\ORM\Locator\TableLocator::get()
     */
    public static function defaultConnectionName(): string
    {
        return 'default';
    }

    /**
     * Initialize a table instance. Called after the constructor.
     * You can use this method to define associations, attach behaviors
     * define validation and do any other initialization logic you need.
     * ```
     *  public function initialize(array $config)
     *  {
     *      $this->belongsTo('Users');
     *      $this->belongsToMany('Tagging.Tags');
     *      $this->setPrimaryKey('something_else');
     *  }
     * ```
     *
     * @param array<string, mixed> $config Configuration options passed to the constructor
     * @return void
     */
    public function initialize(array $config): void
    {
    }

    public function implementedEvents(): array
    {
        $eventMap = [
            'Model.beforeMarshal' => 'beforeMarshal',
            'Model.afterMarshal' => 'afterMarshal',
            'Model.buildValidator' => 'buildValidator',
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeSave' => 'beforeSave',
            'Model.afterSave' => 'afterSave',
            'Model.beforeDelete' => 'beforeDelete',
            'Model.afterDelete' => 'afterDelete',
            'Model.beforeRules' => 'beforeRules',
            'Model.afterRules' => 'afterRules',
        ];
        $events = [];

        foreach ($eventMap as $event => $method) {
            if (!method_exists($this, $method)) {
                continue;
            }
            $events[$event] = $method;
        }

        return $events;
    }

    /**
     * Sets the table alias.
     *
     * @param string $alias Table alias
     * @return $this
     */
    public function setAlias(string $alias)
    {
        $this->_alias = $alias;

        return $this;
    }

    /**
     * Returns the table alias.
     *
     * @return string
     */
    public function getAlias(): string
    {
        if ($this->_alias === null) {
            $alias = namespaceSplit(static::class);
            $alias = substr((string)end($alias), 0, -5) ?: $this->_table;
            if (!$alias) {
                throw new CakeException(
                    'You must specify either the `alias` or the `table` option for the constructor.'
                );
            }
            $this->_alias = $alias;
        }

        return $this->_alias;
    }

    /**
     * Alias a field with the table's current alias.
     * If field is already aliased it will result in no-op.
     *
     * @param string $field The field to alias.
     * @return string The field prefixed with the table alias.
     */
    public function aliasField(string $field): string
    {
        if (str_contains($field, '.')) {
            return $field;
        }

        return $this->getAlias() . '.' . $field;
    }

    /**
     * Sets the table registry key used to create this table instance.
     *
     * @param string $registryAlias The key used to access this object.
     * @return $this
     */
    public function setRegistryAlias(string $registryAlias)
    {
        $this->_registryAlias = $registryAlias;

        return $this;
    }

    /**
     * Returns the table registry key used to create this table instance.
     *
     * @return string
     */
    public function getRegistryAlias(): string
    {
        return $this->_registryAlias ??= $this->getAlias();
    }

    /**
     * Sets the database table name.
     * This can include the database schema name in the form 'schema.table'.
     * If the name must be quoted, enable automatic identifier quoting.
     *
     * @param string $table Table name.
     * @return $this
     */
    public function setTable(string $table)
    {
        $this->_table = $table;

        return $this;
    }

    /**
     * Returns the database table name.
     * This can include the database schema name if set using `setTable()`.
     *
     * @return string
     */
    public function getTable(): string
    {
        if ($this->_table === null) {
            $table = namespaceSplit(static::class);
            $table = substr((string)end($table), 0, -10) ?: $this->_alias;
            if (!$table) {
                throw new CakeException(
                    'You must specify either the `alias` or the `table` option for the constructor.'
                );
            }
            $this->_table = Inflector::underscore($table);
        }

        return $this->_table;
    }

    /**
     * Sets the connection instance.
     *
     * @param \Cake\Database\Connection $connection The connection instance
     * @return $this
     */
    public function setConnection(Connection $connection)
    {
        $this->_connection = $connection;

        return $this;
    }

    /**
     * Returns the connection instance.
     *
     * @return \CakeMongo\Database\MongoConnection
     */
    public function getConnection(): MongoConnection
    {
        if (!$this->_connection) {
            $connection = ConnectionManager::get(static::defaultConnectionName());
            assert($connection instanceof Connection);
            $this->_connection = $connection;
        }

        return $this->_connection;
    }

    /**
     * Sets the schema table object describing this table's properties.
     * If an array is passed, a new TableSchemaInterface will be constructed
     * out of it and used as the schema for this table.
     *
     * @param \Cake\Database\Schema\TableSchemaInterface|array $schema Schema to be used for this table
     * @return $this
     */
    public function setSchema(TableSchemaInterface|array $schema)
    {
        if (is_array($schema)) {
            $constraints = [];

            if (isset($schema['_constraints'])) {
                $constraints = $schema['_constraints'];
                unset($schema['_constraints']);
            }

            $schema = $this->getConnection()->getDriver()->newTableSchema($this->getTable(), $schema);

            foreach ($constraints as $name => $value) {
                $schema->addConstraint($name, $value);
            }
        }

        $this->_schema = $schema;
        if (Configure::read('debug')) {
            $this->checkAliasLengths();
        }

        return $this;
    }

    /**
     * Returns the schema table object describing this table's properties.
     *
     * @return \Cake\Database\Schema\TableSchemaInterface
     */
    public function getSchema(): TableSchemaInterface
    {
        if ($this->_schema === null) {
            $this->_schema = $this->getConnection()
                ->getSchemaCollection()
                ->describe($this->getTable());
            if (Configure::read('debug')) {
                $this->checkAliasLengths();
            }
        }

        $this->_schema->setColumnType('_id', 'uuid');

        /** @var \Cake\Database\Schema\TableSchemaInterface */
        return $this->_schema;
    }

    /**
     * Checks if all table name + column name combinations used for
     * queries fit into the max length allowed by database driver.
     *
     * @return void
     * @throws \Cake\Database\Exception\DatabaseException When an alias combination is too long
     */
    protected function checkAliasLengths(): void
    {
        if ($this->_schema === null) {
            throw new DatabaseException(sprintf(
                'Unable to check max alias lengths for `%s` without schema.',
                $this->getAlias()
            ));
        }

        $maxLength = $this->getConnection()->getDriver()->getMaxAliasLength();
        if ($maxLength === null) {
            return;
        }

        $table = $this->getAlias();
        foreach ($this->_schema->columns() as $name) {
            if (strlen($table . '__' . $name) > $maxLength) {
                $nameLength = $maxLength - 2;
                throw new DatabaseException(
                    'ORM queries generate field aliases using the table name/alias and column name. ' .
                    "The table alias `{$table}` and column `{$name}` create an alias longer than ({$nameLength}). " .
                    'You must change the table schema in the database and shorten either the table or column ' .
                    'identifier so they fit within the database alias limits.'
                );
            }
        }
    }

    /**
     * Test to see if a Table has a specific field/column.
     * Delegates to the schema object and checks for column presence
     * using the Schema\Table instance.
     *
     * @param string $field The field to check for.
     * @return bool True if the field exists, false if it does not.
     */
    public function hasField(string $field): bool
    {
        return $this->getSchema()->getColumn($field) !== null;
    }

    /**
     * Sets the primary key field name.
     *
     * @param list<string>|string $key Sets a new name to be used as primary key
     * @return $this
     */
    public function setPrimaryKey(array|string $key)
    {
        $this->_primaryKey = $key;

        return $this;
    }

    /**
     * Returns the primary key field name.
     *
     * @return list<string>|string
     */
    public function getPrimaryKey(): array|string
    {
        if ($this->_primaryKey === null) {
            $key = $this->getSchema()->getPrimaryKey();
            if (count($key) === 1) {
                $key = $key[0];
            }

            if (!$key) {
                $key = '_id';
            }
            $this->_primaryKey = $key;
        }

        return $this->_primaryKey;
    }

    /**
     * Sets the class used to hydrate rows for this table.
     *
     * @param string $name The name of the class to use
     * @return $this
     * @throws \Cake\ORM\Exception\MissingEntityException when the entity class cannot be found
     */
    public function setEntityClass(string $name)
    {
        /** @var class-string<\Cake\Datasource\EntityInterface>|null $class */
        $class = App::className($name, 'Model/Entity');
        if ($class === null) {
            throw new MissingEntityException([$name]);
        }

        $this->_entityClass = $class;

        return $this;
    }

    /**
     * Returns the class used to hydrate rows for this table.
     *
     * @return class-string<\Cake\Datasource\EntityInterface>
     */
    public function getEntityClass(): string
    {
        if (!$this->_entityClass) {
            $default = Entity::class;
            $self = static::class;
            $parts = explode('\\', $self);

            if ($self === self::class || count($parts) < 3) {
                return $this->_entityClass = $default;
            }

            $alias = Inflector::classify(Inflector::underscore(substr(array_pop($parts), 0, -10)));
            $name = implode('\\', array_slice($parts, 0, -1)) . '\\Entity\\' . $alias;
            if (!class_exists($name)) {
                return $this->_entityClass = $default;
            }

            /** @var class-string<\Cake\Datasource\EntityInterface>|null $class */
            $class = App::className($name, 'Model/Entity');
            if (!$class) {
                throw new MissingEntityException([$name]);
            }

            $this->_entityClass = $class;
        }

        return $this->_entityClass;
    }

    public function find(string $type = 'all', ...$args): QueryInterface
    {
        // TODO: Implement find() method.
    }

    public function get(
        mixed $primaryKey,
        array|string $finder = 'all',
        CacheInterface|string|null $cache = null,
        Closure|string|null $cacheKey = null,
        mixed ...$args
    ): EntityInterface {
        if ($primaryKey === null) {
            throw new InvalidPrimaryKeyException(sprintf(
                'Record not found in table `%s` with primary key `[NULL]`.',
                $this->getTable()
            ));
        }

        $key = (array)$this->getPrimaryKey();
        $alias = $this->getAlias();
        foreach ($key as $index => $keyname) {
            $key[$index] = $alias . '.' . $keyname;
        }
        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }
        if (count($key) !== count($primaryKey)) {
            $primaryKey = $primaryKey ?: [null];
            $primaryKey = array_map(function ($key) {
                return var_export($key, true);
            }, $primaryKey);

            throw new InvalidPrimaryKeyException(sprintf(
                'Record not found in table `%s` with primary key `[%s]`.',
                $this->getTable(),
                implode(', ', $primaryKey)
            ));
        }
        $conditions = array_combine($key, $primaryKey);

        if (is_array($finder)) {
            deprecationWarning(
                '5.0.0',
                'Calling Table::get() with options array is deprecated.'
                . ' Use named arguments instead.'
            );

            $args += $finder;
            $finder = $args['finder'] ?? 'all';
            if (isset($args['cache'])) {
                $cache = $args['cache'];
            }
            if (isset($args['key'])) {
                $cacheKey = $args['key'];
            }
            unset($args['key'], $args['cache'], $args['finder']);
        }

        $query = $this->find($finder, ...$args)->where($conditions);

        if ($cache) {
            if (!$cacheKey) {
                $cacheKey = sprintf(
                    'get-%s-%s-%s',
                    $this->getConnection()->configName(),
                    $this->getTable(),
                    json_encode($primaryKey, JSON_THROW_ON_ERROR)
                );
            }
            $query->cache($cacheKey, $cache);
        }

        return $query->firstOrFail();
    }

    public function query(): QueryInterface
    {
        return $this->selectQuery();
    }

    /**
     * Creates a new select query
     *
     * @return \CakeMongo\ODM\Query\SelectQuery
     */
    public function selectQuery(): SelectQuery
    {
        return $this->queryFactory->select($this);
    }

    /**
     * Creates a new insert query
     *
     * @return \CakeMongo\ODM\Query\InsertQuery
     */
    public function insertQuery(): InsertQuery
    {
        return $this->queryFactory->insert($this);
    }

    public function updateAll(array|Closure|string $fields, array|Closure|string|null $conditions): int
    {
        // TODO: Implement updateAll() method.
    }

    public function deleteAll(array|Closure|string|null $conditions): int
    {
        // TODO: Implement deleteAll() method.
    }

    public function exists(array|Closure|string|null $conditions): bool
    {
        // TODO: Implement exists() method.
    }

    /**
     * {@inheritDoc}
     * ### Options
     * The options array accepts the following keys:
     * - atomic: Whether to execute the save and callbacks inside a database
     *   transaction (default: true)
     * - checkRules: Whether to check the rules on entity before saving, if the checking
     *   fails, it will abort the save operation. (default:true)
     * - associated: If `true` it will save 1st level associated entities as they are found
     *   in the passed `$entity` whenever the property defined for the association
     *   is marked as dirty. If an array, it will be interpreted as the list of associations
     *   to be saved. It is possible to provide different options for saving on associated
     *   table objects using this key by making the custom options the array value.
     *   If `false` no associated records will be saved. (default: `true`)
     * - checkExisting: Whether to check if the entity already exists, assuming that the
     *   entity is marked as not new, and the primary key has been set.
     * ### Events
     * When saving, this method will trigger four events:
     * - Model.beforeRules: Will be triggered right before any rule checking is done
     *   for the passed entity if the `checkRules` key in $options is not set to false.
     *   Listeners will receive as arguments the entity, options array and the operation type.
     *   If the event is stopped the rules check result will be set to the result of the event itself.
     * - Model.afterRules: Will be triggered right after the `checkRules()` method is
     *   called for the entity. Listeners will receive as arguments the entity,
     *   options array, the result of checking the rules and the operation type.
     *   If the event is stopped the checking result will be set to the result of
     *   the event itself.
     * - Model.beforeSave: Will be triggered just before the list of fields to be
     *   persisted is calculated. It receives both the entity and the options as
     *   arguments. The options array is passed as an ArrayObject, so any changes in
     *   it will be reflected in every listener and remembered at the end of the event
     *   so it can be used for the rest of the save operation. Returning false in any
     *   of the listeners will abort the saving process. If the event is stopped
     *   using the event API, the event object's `result` property will be returned.
     *   This can be useful when having your own saving strategy implemented inside a
     *   listener.
     * - Model.afterSave: Will be triggered after a successful insert or save,
     *   listeners will receive the entity and the options array as arguments. The type
     *   of operation performed (insert or update) can be determined by checking the
     *   entity's method `isNew`, true meaning an insert and false an update.
     * - Model.afterSaveCommit: Will be triggered after the transaction is committed
     *   for atomic save, listeners will receive the entity and the options array
     *   as arguments.
     * This method will determine whether the passed entity needs to be
     * inserted or updated in the database. It does that by checking the `isNew`
     * method on the entity. If the entity to be saved returns a non-empty value from
     * its `errors()` method, it will not be saved.
     * ### Saving on associated tables
     * This method will by default persist entities belonging to associated tables,
     * whenever a dirty property matching the name of the property name set for an
     * association in this table. It is possible to control what associations will
     * be saved and to pass additional option for saving them.
     * ```
     * // Only save the comments association
     * $articles->save($entity, ['associated' => ['Comments']]);
     * // Save the company, the employees and related addresses for each of them.
     * // For employees do not check the entity rules
     * $companies->save($entity, [
     *   'associated' => [
     *     'Employees' => [
     *       'associated' => ['Addresses'],
     *       'checkRules' => false
     *     ]
     *   ]
     * ]);
     * // Save no associations
     * $articles->save($entity, ['associated' => false]);
     * ```
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity to be saved
     * @param array<string, mixed> $options The options to use when saving.
     * @return \Cake\Datasource\EntityInterface|false
     * @throws \Cake\ORM\Exception\RolledbackTransactionException If the transaction is aborted in the afterSave event.
     */
    public function save(
        EntityInterface $entity,
        array $options = []
    ): EntityInterface|false {
        $options = new ArrayObject($options + [
                'atomic' => true,
                'associated' => true,
                'checkRules' => true,
                'checkExisting' => true,
                '_primary' => true,
                '_cleanOnSuccess' => true,
            ]);

        if ($entity->hasErrors((bool)$options['associated'])) {
            return false;
        }

        if ($entity->isNew() === false && !$entity->isDirty()) {
            return $entity;
        }

        $success = $this->_processSave($entity, $options);

        if ($success) {
            if ($options['atomic'] || $options['_primary']) {
                if ($options['_cleanOnSuccess']) {
                    $entity->clean();
                    $entity->setNew(false);
                }
                $entity->setSource($this->getRegistryAlias());
            }
        }

        return $success;
    }

    /**
     * Try to save an entity or throw a PersistenceFailedException if the application rules checks failed,
     * the entity contains errors or the save was aborted by a callback.
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity to be saved
     * @param array<string, mixed> $options The options to use when saving.
     * @return \Cake\Datasource\EntityInterface
     * @throws \Cake\ORM\Exception\PersistenceFailedException When the entity couldn't be saved
     * @see \Cake\ORM\Table::save()
     */
    public function saveOrFail(EntityInterface $entity, array $options = []): EntityInterface
    {
        $saved = $this->save($entity, $options);
        if ($saved === false) {
            throw new PersistenceFailedException($entity, ['save']);
        }

        return $saved;
    }

    /**
     * Performs the actual saving of an entity based on the passed options.
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity to be saved
     * @param \ArrayObject<string, mixed> $options the options to use for the save operation
     * @return \Cake\Datasource\EntityInterface|false
     * @throws \Cake\Database\Exception\DatabaseException When an entity is missing some of the primary keys.
     * @throws \Cake\ORM\Exception\RolledbackTransactionException If the transaction
     *   is aborted in the afterSave event.
     */
    protected function _processSave(EntityInterface $entity, ArrayObject $options): EntityInterface|false
    {
        $primaryColumns = (array)$this->getPrimaryKey();

        if ($options['checkExisting'] && $primaryColumns && $entity->isNew() && $entity->has($primaryColumns)) {
            $alias = $this->getAlias();
            $conditions = [];
            foreach ($entity->extract($primaryColumns) as $k => $v) {
                $conditions["{$alias}.{$k}"] = $v;
            }
            $entity->setNew(!$this->exists($conditions));
        }

        $mode = $entity->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;
        if ($options['checkRules'] && !$this->checkRules($entity, $mode, $options)) {
            return false;
        }

        // todo: associations
        // $options['associated'] = $this->_associations->normalizeKeys($options['associated']);
        $event = $this->dispatchEvent('Model.beforeSave', compact('entity', 'options'));

        if ($event->isStopped()) {
            $result = $event->getResult();
            if ($result === null) {
                return false;
            }

            if ($result !== false) {
                assert(
                    $result instanceof EntityInterface,
                    sprintf(
                        'The beforeSave callback must return `false` or `EntityInterface` instance. Got `%s` instead.',
                        get_debug_type($result)
                    )
                );
            }

            return $result;
        }

        // todo: associations
        // $saved = $this->_associations->saveParents(
        //     $this,
        //     $entity,
        //     $options['associated'],
        //     ['_primary' => false] + $options->getArrayCopy()
        // );
        //
        // if (!$saved && $options['atomic']) {
        //     return false;
        // }

        // $data = $entity->extract($this->getSchema()->columns(), true);
        $data = $entity->toArray();
        $isNew = $entity->isNew();

        if ($isNew) {
            $success = $this->_insert($entity, $data);
        } else {
            $success = $this->_update($entity, $data);
        }

        if ($success) {
            $success = $this->_onSaveSuccess($entity, $options);
        }

        if (!$success && $isNew) {
            $entity->unset($this->getPrimaryKey());
            $entity->setNew(true);
        }

        return $success ? $entity : false;
    }

    /**
     * Handles the saving of children associations and executing the afterSave logic
     * once the entity for this table has been saved successfully.
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity to be saved
     * @param \ArrayObject<string, mixed> $options the options to use for the save operation
     * @return bool True on success
     * @throws \Cake\ORM\Exception\RolledbackTransactionException If the transaction
     *   is aborted in the afterSave event.
     */
    protected function _onSaveSuccess(EntityInterface $entity, ArrayObject $options): bool
    {
        // $success = $this->_associations->saveChildren(
        //     $this,
        //     $entity,
        //     $options['associated'],
        //     ['_primary' => false] + $options->getArrayCopy()
        // );
        //
        // if (!$success && $options['atomic']) {
        //     return false;
        // }

        $this->dispatchEvent('Model.afterSave', compact('entity', 'options'));

        if (!$options['atomic'] && !$options['_primary']) {
            $entity->clean();
            $entity->setNew(false);
            $entity->setSource($this->getRegistryAlias());
        }

        return true;
    }

    /**
     * Auxiliary function to handle the insert of an entity's data in the table
     *
     * @param \Cake\Datasource\EntityInterface $entity the subject entity from were $data was extracted
     * @param array $data The actual data that needs to be saved
     * @return \Cake\Datasource\EntityInterface|false
     * @throws \Cake\Database\Exception\DatabaseException if not all the primary keys where supplied or could
     * be generated when the table has composite primary keys. Or when the table has no primary key.
     */
    protected function _insert(EntityInterface $entity, array $data): EntityInterface|false
    {
        $primary = (array)$this->getPrimaryKey();
        if (!$primary) {
            $msg = sprintf(
                'Cannot insert row in `%s` collection, it has no primary key.',
                $this->getTable()
            );
            throw new DatabaseException($msg);
        }
        $keys = array_fill(0, count($primary), null);
        $id = (array)$this->_newId($primary) + $keys;

        // Generate primary keys preferring values in $data.
        $primary = array_combine($primary, $id);
        $primary = array_intersect_key($data, $primary) + $primary;

        $filteredKeys = array_filter($primary, function ($v) {
            return $v !== null;
        });
        $data += $filteredKeys;

        if (count($primary) > 1) {
            $schema = $this->getSchema();
            foreach ($primary as $k => $v) {
                if (!isset($data[$k]) && empty($schema->getColumn($k)['autoIncrement'])) {
                    $msg = 'Cannot insert row, some of the primary key values are missing. ';
                    $msg .= sprintf(
                        'Got (%s), expecting (%s)',
                        implode(', ', $filteredKeys + $entity->extract(array_keys($primary))),
                        implode(', ', array_keys($primary))
                    );
                    throw new DatabaseException($msg);
                }
            }
        }

        if (!$data) {
            return false;
        }

        $statement = $this->insertQuery()->insert(array_keys($data))
            ->values($data)
            ->execute();

        $success = false;
        if ($statement->rowCount() !== 0) {
            $success = $entity;
            $entity->set($filteredKeys, ['guard' => false]);
            $schema = $this->getSchema();
            $driver = $this->getConnection()->getDriver();
            foreach ($primary as $key => $v) {
                if (!isset($data[$key])) {
                    $id = $statement->lastInsertId($this->getTable(), $key);
                    $type = $schema->getColumnType($key);
                    assert($type !== null);
                    $entity->set($key, TypeFactory::build($type)->toPHP($id, $driver));
                    break;
                }
            }
        }

        return $success;
    }

    /**
     * Generate a primary key value for a new record.
     * By default, this uses the type system to generate a new primary key
     * value if possible. You can override this method if you have specific requirements
     * for id generation.
     * Note: The ORM will not generate primary key values for composite primary keys.
     * You can overwrite _newId() in your table class.
     *
     * @param list<string> $primary The primary key columns to get a new ID for.
     * @return string|null Either null or the primary key value or a list of primary key values.
     */
    protected function _newId(array $primary): ?string
    {
        if (!$primary || count($primary) > 1) {
            return null;
        }
        $typeName = $this->getSchema()->getColumnType($primary[0]);
        assert($typeName !== null);
        $type = TypeFactory::build($typeName);

        return $type->newId();
    }

    public function delete(EntityInterface $entity, array $options = []): bool
    {
        // TODO: Implement delete() method.
    }

    /**
     * Get the object used to marshal/convert array data into objects.
     * Override this method if you want a table object to use custom
     * marshalling logic.
     *
     * @return \Cake\ORM\Marshaller
     * @see \Cake\ORM\Marshaller
     */
    public function marshaller(): Marshaller
    {
        return new Marshaller($this);
    }

    public function newEmptyEntity(): EntityInterface
    {
        // TODO: Implement newEmptyEntity() method.
    }

    public function newEntity(array $data, array $options = []): EntityInterface
    {
        // TODO: Implement newEntity() method.
    }

    public function newEntities(array $data, array $options = []): array
    {
        // TODO: Implement newEntities() method.
    }

    /**
     * {@inheritDoc}
     * When merging HasMany or BelongsToMany associations, all the entities in the
     * `$data` array will appear, those that can be matched by primary key will get
     * the data merged, but those that cannot, will be discarded.
     * You can limit fields that will be present in the merged entity by
     * passing the `fields` option, which is also accepted for associations:
     * ```
     * $article = $this->Articles->patchEntity($article, $this->request->getData(), [
     *  'fields' => ['title', 'body', 'tags', 'comments'],
     *  'associated' => ['Tags', 'Comments.Users' => ['fields' => 'username']]
     *  ]
     * );
     * ```
     * ```
     * $article = $this->Articles->patchEntity($article, $this->request->getData(), [
     *   'associated' => [
     *     'Tags' => ['accessibleFields' => ['*' => true]]
     *   ]
     * ]);
     * ```
     * By default, the data is validated before being passed to the entity. In
     * the case of invalid fields, those will not be assigned to the entity.
     * The `validate` option can be used to disable validation on the passed data:
     * ```
     * $article = $this->patchEntity($article, $this->request->getData(),[
     *  'validate' => false
     * ]);
     * ```
     * You can use the `Model.beforeMarshal` event to modify request data
     * before it is converted into entities.
     * When patching scalar values (null/booleans/string/integer/float), if the property
     * presently has an identical value, the setter will not be called, and the
     * property will not be marked as dirty. This is an optimization to prevent unnecessary field
     * updates when persisting entities.
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity that will get the
     * data merged in
     * @param array $data key value list of fields to be merged into the entity
     * @param array<string, mixed> $options A list of options for the object hydration.
     * @return \Cake\Datasource\EntityInterface
     * @see \Cake\ORM\Marshaller::merge()
     */
    public function patchEntity(EntityInterface $entity, array $data, array $options = []): EntityInterface
    {
        // $options['associated'] ??= $this->_associations->keys();

        return $this->marshaller()->merge($entity, $data, $options);
    }

    public function patchEntities(iterable $entities, array $data, array $options = []): array
    {
        // TODO: Implement patchEntities() method.
    }
}
