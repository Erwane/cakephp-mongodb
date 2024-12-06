<?php
declare(strict_types=1);

namespace CakeMongo\ODM\Query;

interface QueryInterface
{
    public function callable(): callable;
}
