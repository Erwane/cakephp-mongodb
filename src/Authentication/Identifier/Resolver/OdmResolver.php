<?php
declare(strict_types=1);

namespace CakeMongo\Authentication\Identifier\Resolver;

use ArrayAccess;
use Authentication\Identifier\Resolver\ResolverInterface;
use Cake\Core\InstanceConfigTrait;
use CakeMongo\ODM\Locator\LocatorAwareTrait;

class OdmResolver implements ResolverInterface
{
    use InstanceConfigTrait;
    use LocatorAwareTrait;

    /**
     * Default configuration.
     * - `userModel` The alias for users table, defaults to Users.
     * - `finder` The finder method to use to fetch user record. Defaults to 'all'.
     *   You can set finder name as string or an array where key is finder name and value
     *   is an array passed to `Table::find()` options.
     *   E.g. ['finderName' => ['some_finder_option' => 'some_value']]
     *
     * @var array
     */
    protected array $_defaultConfig = [
        'userModel' => 'Users',
        'finder' => 'all',
    ];

    /**
     * Constructor.
     *
     * @param array $config Config array.
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    public function find(array $conditions, string $type = self::TYPE_AND): ArrayAccess|array|null
    {
        $table = $this->getTableLocator()->get($this->_config['userModel']);

        $query = $table->selectQuery();
        $finders = (array)$this->_config['finder'];
        foreach ($finders as $finder => $options) {
            if (is_string($options)) {
                $query->find($options);
            } else {
                $query->find($finder, ...$options);
            }
        }

        $where = [];
        foreach ($conditions as $field => $value) {
            $field = $table->aliasField($field);
            if (is_array($value)) {
                $field = $field . ' IN';
            }
            $where[$field] = $value;
        }

        return $query->where([$type => $where])->first();
    }
}
