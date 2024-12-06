<?php
declare(strict_types=1);

namespace CakeMongo\Test\TestCase\ODM;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use CakeMongo\Test\Factory\UserFactory;

/**
 * @uses \CakeMongo\ODM\Collection
 * @covers \CakeMongo\ODM\Collection
 */
class CollectionTest extends TestCase
{
    protected \Cake\Datasource\ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
    }

    /**
     * Tests query creation wrappers.
     */
    public function testTableQuery(): void
    {
        // $table = $this->fetchTable('users');
        // debug($table);
        // $table = new Collection(['table' => 'users']);

        // $query = $table->find();
        // $this->assertEquals('users', $query->getRepository()->getTable());
    }

    public function testFind()
    {
        $user = UserFactory::make()->getEntity();
        debug($user);
    }
}
