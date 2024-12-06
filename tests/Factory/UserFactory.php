<?php
declare(strict_types=1);

namespace CakeMongo\Test\Factory;

use CakephpFixtureFactories\Factory\BaseFactory;
use Faker\Generator;

class UserFactory extends BaseFactory
{

    protected function getRootTableRegistryName(): string
    {
        return 'Users';
    }

    protected function setDefaultTemplate(): void
    {
        $this->setDefaultData(function (Generator $faker) {
            return [
                'email' => $faker->email,
            ];
        });
    }
}
