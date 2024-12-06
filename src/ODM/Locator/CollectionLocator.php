<?php
declare(strict_types=1);

namespace CakeMongo\ODM\Locator;

use Cake\Core\App;
use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Locator\AbstractLocator;
use Cake\ORM\Exception\MissingTableClassException;
use Cake\Utility\Inflector;
use CakeMongo\ODM\Collection;

class CollectionLocator extends AbstractLocator implements LocatorInterface
{
    protected array $locations;

    /**
     * Configuration for aliases.
     *
     * @var array<string, array|null>
     */
    protected array $_config = [];

    /**
     * Fallback class to use
     *
     * @var string
     * @psalm-var class-string<\CakeMongo\ODM\Collection>
     */
    protected string $fallbackClassName = Collection::class;

    protected bool $allowFallbackClass = true;

    public function __construct(?array $locations = null)
    {
        if ($locations === null) {
            $locations = [
                'Model/Collection',
            ];
        }

        foreach ($locations as $location) {
            $this->addLocation($location);
        }
    }

    /**
     * Set if fallback class should be used.
     *
     * Controls whether a fallback class should be used to create a table
     * instance if a concrete class for alias used in `get()` could not be found.
     *
     * @param bool $allow Flag to enable or disable fallback
     * @return $this
     */
    public function allowFallbackClass(bool $allow)
    {
        $this->allowFallbackClass = $allow;

        return $this;
    }

    /**
     * Adds a location where tables should be looked for.
     *
     * @param string $location Location to add.
     * @return $this
     * @since 3.8.0
     */
    public function addLocation(string $location)
    {
        $location = str_replace('\\', '/', $location);
        $this->locations[] = trim($location, '/');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setConfig(array|string $alias, ?array $options = null)
    {
        if (!is_string($alias)) {
            $this->_config = $alias;

            return $this;
        }

        if (isset($this->instances[$alias])) {
            throw new DatabaseException(sprintf(
                'You cannot configure `%s`, it has already been constructed.',
                $alias
            ));
        }

        $this->_config[$alias] = $options;

        return $this;
    }

    public function get(string $alias, array $options = []): Collection
    {
        /** @var \CakeMongo\ODM\Collection */
        return parent::get($alias, $options);
    }

    /**
     * @inheritDoc
     */
    protected function createInstance(string $alias, array $options): Collection
    {
        if (!str_contains($alias, '\\')) {
            [, $classAlias] = pluginSplit($alias);
            $options = ['alias' => $classAlias] + $options;
        } elseif (!isset($options['alias'])) {
            $options['className'] = $alias;
        }

        if (isset($this->_config[$alias])) {
            $options += $this->_config[$alias];
        }

        $allowFallbackClass = $options['allowFallbackClass'] ?? $this->allowFallbackClass;
        $className = $this->_getClassName($alias, $options);
        if ($className) {
            $options['className'] = $className;
        } elseif ($allowFallbackClass) {
            if (empty($options['className'])) {
                $options['className'] = $alias;
            }
            if (!isset($options['table']) && !str_contains($options['className'], '\\')) {
                [, $table] = pluginSplit($options['className']);
                $options['table'] = Inflector::underscore($table);
            }
            $options['className'] = $this->fallbackClassName;
        } else {
            $message = $options['className'] ?? $alias;
            $message = '`' . $message . '`';
            if (!str_contains($message, '\\')) {
                $message = 'for alias ' . $message;
            }
            throw new MissingTableClassException([$message]);
        }

        if (empty($options['connection'])) {
            if (!empty($options['connectionName'])) {
                $connectionName = $options['connectionName'];
            } else {
                /** @var \CakeMongo\ODM\Collection $className */
                $className = $options['className'];
                $connectionName = $className::defaultConnectionName();
            }
            $options['connection'] = ConnectionManager::get($connectionName);
        }
        // Todo : associations ?
        // if (empty($options['associations'])) {
        //     $associations = new AssociationCollection($this);
        //     $options['associations'] = $associations;
        // }

        $options['registryAlias'] = $alias;

        return $this->_create($options);
    }

    /**
     * Gets the table class name.
     *
     * @param string $alias The alias name you want to get. Should be in CamelCase format.
     * @param array<string, mixed> $options Table options array.
     * @return string|null
     */
    protected function _getClassName(string $alias, array $options = []): ?string
    {
        if (empty($options['className'])) {
            $options['className'] = $alias;
        }

        if (str_contains($options['className'], '\\') && class_exists($options['className'])) {
            return $options['className'];
        }

        foreach ($this->locations as $location) {
            $class = App::className($options['className'], $location, 'Collection');
            if ($class !== null) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Wrapper for creating table instances
     *
     * @param array<string, mixed> $options The alias to check for.
     * @return \CakeMongo\ODM\Collection
     */
    protected function _create(array $options): Collection
    {
        /** @var class-string<\CakeMongo\ODM\Collection> $class */
        $class = $options['className'];

        return new $class($options);
    }

    public function getConfig(?string $alias = null): array
    {
        // TODO: Implement getConfig() method.
    }
}
