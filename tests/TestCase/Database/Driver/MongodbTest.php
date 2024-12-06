<?php
declare(strict_types=1);

namespace CakeMongo\Test\TestCase\Database\Driver;

use Cake\TestSuite\TestCase;
use CakeMongo\Database\Driver\Mongodb;

/**
 * Mongodb driver tests.
 *
 * @uses \CakeMongo\Database\Driver\Mongodb
 * @covers \CakeMongo\Database\Driver\Mongodb
 */
class MongodbTest extends TestCase
{
    /**
     * Test connecting to database
     */
    public function testConnectionConfigDefault(): void
    {
        $driver = $this->getMockBuilder(Mongodb::class)
            ->onlyMethods(['_connect'])
            ->getMock();

        $dsn = 'mongodb://root:@localhost:27017/cake';
        $expected = [
            'persistent' => true,
            'host' => 'localhost',
            'username' => 'root',
            'password' => '',
            'database' => 'cake',
            'port' => 27017,
        ];

        $driver->expects($this->once())
            ->method('_connect')
            ->with($dsn, $expected);

        $driver->connect();
    }

    /**
     * Test mongodb enabled
     */
    public function testEnabled()
    {
        $driver = new Mongodb();
        $this->assertTrue($driver->enabled());
    }

    /**
     * Test queryTranslation.
     */
    public function testQueryTranslator()
    {
        $driver = new Mongodb();
        $closure = $driver->queryTranslator('select');
        $this->assertInstanceOf(\Closure::class, $closure);
    }
}
