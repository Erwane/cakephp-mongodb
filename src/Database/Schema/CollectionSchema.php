<?php
declare(strict_types=1);

namespace CakeMongo\Database\Schema;

use Cake\Database\Schema\TableSchema;

class CollectionSchema extends TableSchema
{
    protected static array $_columnKeys = [
        'type' => null,
    ];
}
