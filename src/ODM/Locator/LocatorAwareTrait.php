<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.1.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace CakeMongo\ODM\Locator;

use Cake\Datasource\FactoryLocator;
use CakeMongo\ODM\Collection;
use UnexpectedValueException;

/**
 * Contains method for setting and accessing LocatorInterface instance
 */
trait LocatorAwareTrait
{
    /**
     * This object's default table alias.
     *
     * @var string|null
     */
    protected ?string $defaultTable = null;

    /**
     * Table locator instance
     *
     * @var \CakeMongo\ODM\Locator\CollectionLocator|null
     */
    protected ?CollectionLocator $_tableLocator = null;

    /**
     * Sets the table locator.
     *
     * @param \CakeMongo\ODM\Locator\CollectionLocator $tableLocator LocatorInterface instance.
     * @return $this
     */
    public function setTableLocator(CollectionLocator $tableLocator)
    {
        $this->_tableLocator = $tableLocator;

        return $this;
    }

    /**
     * Gets the table locator.
     *
     * @return \CakeMongo\ODM\Locator\CollectionLocator
     */
    public function getTableLocator(): CollectionLocator
    {
        if ($this->_tableLocator !== null) {
            return $this->_tableLocator;
        }

        $locator = FactoryLocator::get('Collection');
        assert(
            $locator instanceof CollectionLocator,
            '`FactoryLocator` must return an instance of CakeMongo\ODM\CollectionLocator for type `Collection`.'
        );

        return $this->_tableLocator = $locator;
    }

    /**
     * Convenience method to get a table instance.
     *
     * @param string|null $alias The alias name you want to get. Should be in CamelCase format.
     *  If `null` then the value of $defaultTable property is used.
     * @param array<string, mixed> $options The options you want to build the table with.
     *   If a table has already been loaded the registry options will be ignored.
     * @return \CakeMongo\ODM\Collection
     * @see \Cake\ORM\TableLocator::get()
     * @since 4.5
     */
    public function fetchTable(?string $alias = null, array $options = []): Collection
    {
        $alias ??= $this->defaultTable;
        if (!$alias) {
            throw new UnexpectedValueException(
                'You must provide an `$alias` or set the `$defaultTable` property to a non empty string.'
            );
        }

        return $this->getTableLocator()->get($alias, $options);
    }
}
