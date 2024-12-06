<?php
declare(strict_types=1);

namespace CakeMongo\Database\Schema;

use Cake\Database\Exception\DatabaseException;
use Cake\Database\Schema\Collection as CakeCollection;
use Cake\Database\Schema\TableSchema;
use Cake\Database\Schema\TableSchemaInterface;
use MongoDB\Driver\Exception\Exception;

class Collection extends CakeCollection
{
    /**
     * @inheritDoc
     */
    public function describe(string $name, array $options = []): TableSchemaInterface
    {
        $config = $this->_connection->config();
        if (str_contains($name, '.')) {
            [$config['schema'], $name] = explode('.', $name);
        }
        $schema = $this->_connection->getDriver()->newTableSchema($name);

        $this->_reflect('Column', $name, $config, $schema);
        $this->_reflect('Index', $name, $config, $schema);
        $this->_reflect('ForeignKey', $name, $config, $schema);
        $this->_reflect('Options', $name, $config, $schema);

        $this->_minimalSchema($schema);

        return $schema;
    }

    /**
     * Helper method for running each step of the reflection process.
     *
     * @param string $stage The stage name.
     * @param string $name The table name.
     * @param array<string, mixed> $config The config data.
     * @param \Cake\Database\Schema\TableSchemaInterface $schema The table schema instance.
     * @return void
     * @throws \Cake\Database\Exception\DatabaseException on query failure.
     * @uses \CakeMongo\Database\Schema\MongoSchemaDialect::describeColumnSql
     * @uses \CakeMongo\Database\Schema\MongoSchemaDialect::describeIndexSql
     * @uses \CakeMongo\Database\Schema\MongoSchemaDialect::describeForeignKeySql
     * @uses \CakeMongo\Database\Schema\MongoSchemaDialect::describeOptionsSql
     * @uses \CakeMongo\Database\Schema\MongoSchemaDialect::convertColumnDescription
     * @uses \CakeMongo\Database\Schema\MongoSchemaDialect::convertIndexDescription
     * @uses \CakeMongo\Database\Schema\MongoSchemaDialect::convertForeignKeyDescription
     * @uses \CakeMongo\Database\Schema\MongoSchemaDialect::convertOptionsDescription
     */
    protected function _reflect(string $stage, string $name, array $config, TableSchemaInterface $schema): void
    {
        try {
            parent::_reflect($stage, $name, $config, $schema);
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 500, $e);
        }
    }

    protected function _minimalSchema(TableSchemaInterface $schema): void
    {
        if (!$schema->columns()) {
            $schema->addColumn('_id', 'string');
        }
        if (!$schema->constraints()) {
            $schema->addConstraint('_id', [
                'type' => TableSchema::CONSTRAINT_PRIMARY,
                'columns' => ['_id'],
            ]);
        }
    }
}
