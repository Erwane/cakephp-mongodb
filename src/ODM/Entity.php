<?php
declare(strict_types=1);

namespace CakeMongo\ODM;

/**
 * @property string $id
 */
class Entity extends \Cake\ORM\Entity
{
    protected function _getId(): ?string
    {
        return $this->_fields['_id'] ?? null;
    }
}
