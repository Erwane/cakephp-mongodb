<?php
declare(strict_types=1);

namespace CakeMongo\Database;

use Cake\Database\Connection as CakeConnection;
use Cake\Database\Schema\CachedCollection;
use Cake\Database\Schema\CollectionInterface;
use CakeMongo\Database\Driver\Mongo;
use CakeMongo\Database\Schema\Collection as SchemaCollection;
use Closure;

class MongoConnection extends CakeConnection
{
    /**
     * @inheritDoc
     */
    protected function createDrivers(array $config): array
    {
        $config['driver'] = Mongo::class;

        return parent::createDrivers($config); // TODO: Change the autogenerated stub
    }

    /**
     * Mongo doesn't support transactions
     *
     * @param \Closure $callback Transaction callback
     * @return mixed
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function transactional(Closure $callback): mixed
    {
        return false;
    }

    /**
     * Mongo doesn't support foreign keys.
     *
     * @param \Closure $callback Transaction callback
     * @return false
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function disableConstraints(Closure $callback): mixed
    {
        return false;
    }

    /**
     * Gets a Schema\Collection object for this connection.
     *
     * @return \Cake\Database\Schema\CollectionInterface
     */
    public function getSchemaCollection(): CollectionInterface
    {
        if ($this->_schemaCollection !== null) {
            return $this->_schemaCollection;
        }

        if (!empty($this->_config['cacheMetadata'])) {
            return $this->_schemaCollection = new CachedCollection(
                new SchemaCollection($this),
                empty($this->_config['cacheKeyPrefix']) ? $this->configName() : $this->_config['cacheKeyPrefix'],
                $this->getCacher()
            );
        }

        return $this->_schemaCollection = new SchemaCollection($this);
    }
}
