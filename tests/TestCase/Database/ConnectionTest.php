<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);

namespace CakeMongo\Test\TestCase\Database;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use CakeMongo\Database\Connection;
use CakeMongo\Database\Driver\Mongodb;

/**
 * Mongo connection tests.
 *
 * @uses   \CakeMongo\Database\Connection
 * @covers \CakeMongo\Database\Connection
 */
class ConnectionTest extends TestCase
{
    public function testGetFromDsn()
    {
        $dsn = Connection::class . '://localhost:3306/database?driver=mongodb';
        ConnectionManager::setConfig('testing', ['url' => $dsn]);

        $connection = ConnectionManager::get('testing');

        $this->assertInstanceOf(Connection::class, $connection);
    }

    /**
     * Get clean connection.
     *
     * @param string $name Connection name.
     * @return \Cake\Datasource\ConnectionInterface
     */
    protected function getConnection(string $name)
    {
        $config = ConnectionManager::getConfig($name);
        if (!$config) {
            $config = [
                'className' => Connection::class,
                'driver' => Mongodb::class,
                'host' => 'localhost',
                'username' => 'root',
                'password' => '',
                'database' => 'cake',
                'port' => 27017,
            ];
            ConnectionManager::setConfig($name, $config);
        }

        return ConnectionManager::get($name);
    }

    /**
     * Test transactional.
     */
    public function testTransactional()
    {
        $con = self::getConnection('testing');
        $this->assertFalse($con->transactional(fn () => true));
    }

    /**
     * Test transactional.
     */
    public function testDisableConstraints()
    {
        $con = self::getConnection('testing');
        $this->assertFalse($con->disableConstraints(fn () => true));
    }
}
