<?php
declare(strict_types=1);

namespace CakeMongo\Database\Schema;

use Cake\Database\Schema\SchemaDialect;
use Cake\Database\Schema\TableSchema;

class MongoSchemaDialect extends SchemaDialect
{
    /**
     * The driver instance being used.
     *
     * @var \CakeMongo\Database\Driver\Mongodb
     */
    protected $_driver;

    public function listTablesSql(array $config): array
    {
        return [];
    }

    public function describeColumnSql(string $tableName, array $config): array
    {
        return [];
    }

    public function describeIndexSql(string $tableName, array $config): array
    {
        return [];
    }

    public function describeForeignKeySql(string $tableName, array $config): array
    {
        return [];
    }

    public function convertColumnDescription(TableSchema $schema, array $row): void
    {
    }

    public function convertIndexDescription(TableSchema $schema, array $row): void
    {
    }

    public function convertForeignKeyDescription(TableSchema $schema, array $row): void
    {
    }

    public function createTableSql(TableSchema $schema, array $columns, array $constraints, array $indexes): array
    {
        return [];
    }

    public function columnSql(TableSchema $schema, string $name): string
    {
        return '';
    }

    public function addConstraintSql(TableSchema $schema): array
    {
        return [];
    }

    public function dropConstraintSql(TableSchema $schema): array
    {
        return [];
    }

    public function constraintSql(TableSchema $schema, string $name): string
    {
        return '';
    }

    public function indexSql(TableSchema $schema, string $name): string
    {
        return '';
    }

    public function truncateTableSql(TableSchema $schema): array
    {
        return [];
    }
}
