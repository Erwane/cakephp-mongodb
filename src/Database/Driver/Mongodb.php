<?php
declare(strict_types=1);

namespace CakeMongo\Database\Driver;

use Cake\Core\App;
use Cake\Database\Driver;
use Cake\Database\Exception\MissingConnectionException;
use Cake\Database\Schema\SchemaDialect;
use CakeMongo\Database\Schema\MongoSchemaDialect;
use Closure;
use Exception;
use MongoDB\Client;

class Mongodb extends Driver
{
    /**
     * Base configuration settings for MySQL driver
     *
     * @var array<string, mixed>
     */
    protected $_baseConfig = [
        'persistent' => true,
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'cake',
        'port' => 27017,
    ];

    /**
     * @var \MongoDB\Client
     */
    protected $_connection;

    /**
     * The schema dialect for this driver
     *
     * @var \Cake\Database\Schema\SchemaDialect|null
     */
    protected ?SchemaDialect $_schemaDialect = null;

    /**
     * @inheritDoc
     */
    public function connect(): bool
    {
        if ($this->_connection) {
            return true;
        }

        $dsn = $this->buildDsn();
        $this->_connection = $this->_connect($dsn, $this->_config);

        return true;
    }

    /**
     * Build Dsn
     *
     * @return string
     */
    protected function buildDsn(): string
    {
        $credentials = '';
        if (!empty($this->_config['username'])) {
            $credentials = $this->_config['username'] . ':' . $this->_config['password'] . '@';
        }

        $host = $this->_config['host'] . ':' . $this->_config['port'];

        return sprintf(
            'mongodb://%s%s/%s',
            $credentials,
            $host,
            $this->_config['database']
        );
    }

    /**
     * Create connection, a MongoDB Client
     *
     * @param string $dsn A Driver-specific PDO-DSN
     * @param array<string, mixed> $config configuration to be used for creating connection
     * @return bool true on success
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function _connect(string $dsn, array $config): bool
    {
        try {
            $this->_connection = new Client($dsn);

            $this->_connection->selectDatabase($config['database']);
        } catch (Exception $e) {
            throw new MissingConnectionException(
                [
                    'driver' => App::shortName(static::class, 'Database/Driver'),
                    'reason' => $e->getMessage(),
                ],
                null,
                $e
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function enabled(): bool
    {
        return defined('MONGODB_VERSION')
            && version_compare(MONGODB_VERSION, '1.16.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function queryTranslator(string $type): Closure
    {
        return function ($query) use ($type) {
            // Nothing to do.
        };
    }

    /**
     * @inheritDoc
     */
    public function schemaDialect(): SchemaDialect
    {
        if ($this->_schemaDialect === null) {
            $this->_schemaDialect = new MongoSchemaDialect($this);
        }

        return $this->_schemaDialect;
    }

    public function quoteIdentifier(string $identifier): string
    {
        // TODO: Implement quoteIdentifier() method.
    }

    public function releaseSavePointSQL($name): string
    {
        // TODO: Implement releaseSavePointSQL() method.
    }

    public function savePointSQL($name): string
    {
        // TODO: Implement savePointSQL() method.
    }

    public function rollbackSavePointSQL($name): string
    {
        // TODO: Implement rollbackSavePointSQL() method.
    }

    public function disableForeignKeySQL(): string
    {
        // TODO: Implement disableForeignKeySQL() method.
    }

    public function enableForeignKeySQL(): string
    {
        // TODO: Implement enableForeignKeySQL() method.
    }

    public function supportsDynamicConstraints(): bool
    {
        // TODO: Implement supportsDynamicConstraints() method.
    }
}
