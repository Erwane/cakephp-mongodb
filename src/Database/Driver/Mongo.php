<?php
declare(strict_types=1);

namespace CakeMongo\Database\Driver;

use Cake\Core\App;
use Cake\Database\Driver;
use Cake\Database\DriverFeatureEnum;
use Cake\Database\Exception\MissingConnectionException;
use Cake\Database\Query;
use Cake\Database\ValueBinder;
use Cake\Database\Schema\SchemaDialect;
use Cake\Database\StatementInterface;
use CakeMongo\Database\QueryCompiler;
use CakeMongo\Database\Schema\CollectionSchema;
use CakeMongo\Database\Schema\MongoSchemaDialect;
use CakeMongo\Database\Statement\MongoStatement;
use CakeMongo\ODM\Query\SelectQuery;
use Closure;
use Exception;
use MongoDB\Client;
use MongoDB\Database;

/**
 * MongoDB database driver
 */
class Mongo extends Driver
{
    protected const STATEMENT_CLASS = MongoStatement::class;

    /**
     * Base configuration settings for MySQL driver
     *
     * @var array<string, mixed>
     */
    protected array $_baseConfig = [
        'persistent' => true,
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'cake',
        'port' => 27017,
        'tableSchema' => CollectionSchema::class,
    ];

    /**
     * @var \MongoDB\Client|null
     */
    protected ?Client $client = null;

    /**
     * @var \MongoDB\Database|null
     */
    protected ?Database $db = null;

    /**
     * The schema dialect for this driver
     *
     * @var \Cake\Database\Schema\SchemaDialect
     */
    protected SchemaDialect $_schemaDialect;

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        if ($this->client) {
            return;
        }

        try {
            $dsn = $this->buildDsn();
            $this->client = new Client($dsn);

            $this->db = $this->client->selectDatabase($this->_config['database']);
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
            'mongodb://%s%s',
            $credentials,
            $host
        );
    }

    /**
     * Return database connection.
     *
     * @return \MongoDB\Database|null
     */
    public function getDatabase(): ?Database
    {
        $this->connect();

        return $this->db;
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
        return function ($query) use ($type): void {
            // Nothing to do.
        };
    }

    /**
     * @inheritDoc
     */
    public function run(Query $query): StatementInterface
    {
        $callable = $query->callable();

        if ($query instanceof SelectQuery) {
            $pipeline = $query->build();
            try {
                $results = $callable($pipeline);
            } catch (\Exception $e) {
                debug($e);
                exit;
            }
        } else {
            $values = $query->clause('values');
            $results = $callable($values->getValues());
        }
        $statement = new MongoStatement($results, $this, $this->getResultSetDecorators($query));

        return $statement;
    }

    /**
     * @inheritDoc
     */
    public function schemaDialect(): MongoSchemaDialect
    {
        return $this->_schemaDialect ?? ($this->_schemaDialect = new MongoSchemaDialect($this));
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

    public function supports(DriverFeatureEnum $feature): bool
    {
        // TODO: Implement supports() method.
    }

    /**
     * @return \CakeMongo\Database\QueryCompiler
     */
    public function newCompiler(): QueryCompiler
    {
        return new QueryCompiler();
    }

    public function buildQuery(Query $query, ValueBinder $binder): array
    {
        $processor = $this->newCompiler();
        $query = $this->transformQuery($query);

        return $processor->build($query, $binder);
    }

}
