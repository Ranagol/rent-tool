<?php

namespace App\Factory;

use App\Entity\Customer;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Customer>
 */
final class CustomerFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Customer::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->name(),
            'email' => self::faker()->unique()->safeEmail(),
            'phone' => self::faker()->numerify('+43 ### #######'),
            'address' => self::faker()->streetAddress(),
            'city' => self::faker()->city(),
            'postalCode' => self::faker()->numerify('####'),
            'comments' => self::faker()->optional(0.3)->sentence(),
        ];
    }
}
