<?php
declare(strict_types=1);

namespace CakeMongo\Database\Statement;

use Cake\Database\Driver;
use Cake\Database\StatementInterface;
use CakeMongo\Database\Driver\Mongo;
use MongoDB\DeleteResult;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use PDO;
use Traversable;

class MongoStatement implements StatementInterface
{
    /**
     * @var \MongoDB\InsertOneResult|\MongoDB\InsertManyResult|\MongoDB\DeleteResult|\MongoDB\UpdateResult
     */
    protected mixed $mongo;

    /**
     * @var \Cake\Database\Driver|\CakeMongo\Database\Driver\Mongo
     */
    protected Driver|Mongo $_driver;

    /**
     * @param mixed $mongo
     * @param \Cake\Database\Driver|\CakeMongo\Database\Driver\Mongo $driver
     * @param array $resultDecorators
     */
    public function __construct(mixed $mongo, Mongo|Driver $driver, protected array $resultDecorators = [])
    {
        $this->mongo = $mongo;
        $this->_driver = $driver;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        // TODO: Implement getIterator() method.
    }

    /**
     * @inheritDoc
     */
    public function bindValue(int|string $column, mixed $value, int|string|null $type = 'string'): void
    {
        debug(__METHOD__);
        $type ??= 'string';
        if (!is_int($type)) {
            [$value, $type] = $this->cast($value, $type);
        }

        $this->params[$column] = $value;
        $this->performBind($column, $value, $type);
    }

    /**
     * Converts a give value to a suitable database value based on type and
     * return relevant internal statement type.
     *
     * @param mixed $value The value to cast.
     * @param \Cake\Database\TypeInterface|string|int $type The type name or type instance to use.
     * @return array List containing converted value and internal type.
     * @psalm-return array{0:mixed, 1:int}
     */
    protected function cast(mixed $value, TypeInterface|string|int $type = 'string'): array
    {
        if (is_string($type)) {
            $type = TypeFactory::build($type);
        }
        if ($type instanceof TypeInterface) {
            $value = $type->toDatabase($value, $this->_driver);
            $type = $type->toStatement($value, $this->_driver);
        }

        return [$value, $type];
    }

    /**
     * @inheritDoc
     */
    public function closeCursor(): void
    {
        // TODO: Implement closeCursor() method.
    }

    /**
     * @inheritDoc
     */
    public function columnCount(): int
    {
        // TODO: Implement columnCount() method.
    }

    /**
     * @inheritDoc
     */
    public function errorCode(): string
    {
        // TODO: Implement errorCode() method.
    }

    /**
     * @inheritDoc
     */
    public function errorInfo(): array
    {
        // TODO: Implement errorInfo() method.
    }

    /**
     * @inheritDoc
     */
    public function execute(?array $params = null): bool
    {
        // TODO: Implement execute() method.
    }

    /**
     * @inheritDoc
     */
    public function fetch(int|string $mode = PDO::FETCH_NUM): mixed
    {
        // TODO: Implement fetch() method.
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(int|string $mode = PDO::FETCH_NUM): array
    {
        $rows = [];

        foreach ($this->mongo as $item) {
            $rows[] = $item->getArrayCopy();
        }

        foreach ($this->resultDecorators as $decorator) {
            $rows = array_map($decorator, $rows);
        }

        return $rows;
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn(int $position): mixed
    {
        // TODO: Implement fetchColumn() method.
    }

    /**
     * @inheritDoc
     */
    public function fetchAssoc(): array
    {
        // TODO: Implement fetchAssoc() method.
    }

    /**
     * @inheritDoc
     */
    public function rowCount(): int
    {
        return match (get_class($this->mongo)) {
            InsertOneResult::class, InsertManyResult::class => $this->mongo->getInsertedCount(),
            UpdateResult::class => $this->mongo->getMatchedCount(),
            DeleteResult::class => $this->mongo->getDeletedCount(),
            default => 0,
        };
    }

    /**
     * @inheritDoc
     */
    public function bind(array $params, array $types): void
    {
        // TODO: Implement bind() method.
    }

    /**
     * @inheritDoc
     */
    public function lastInsertId(?string $table = null, ?string $column = null): string|int
    {
        // TODO: Implement lastInsertId() method.
    }

    /**
     * @inheritDoc
     */
    public function queryString(): string
    {
        // TODO: Implement queryString() method.
    }

    /**
     * @inheritDoc
     */
    public function getBoundParams(): array
    {
        // TODO: Implement getBoundParams() method.
    }
}
