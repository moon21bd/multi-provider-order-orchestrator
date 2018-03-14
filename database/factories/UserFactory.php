<?php // database/factories/UserFactory.php

use Ramsey\Uuid\Uuid;

$factory->define(App\Models\User::class, function (Faker\Generator $faker) {
    return [
        'uid'                   => Uuid::uuid5(Uuid::NAMESPACE_DNS, 'fosterpayments.com')->toString(),
        'firstName'             => $faker->firstName,
        'lastName'              => $faker->lastName,
        'email'                 => $faker->email,
        'middleName'            => $faker->lastName,
        'password'              => \Illuminate\Support\Facades\Hash::make('test-password'),
        'address'               => $faker->address,
        'zipCode'               => $faker->postcode,
        'username'              => $faker->userName,
        'balance'              => 0,
        'org_id'              => 1,
        'org_code'              => '',
        'city'                  => $faker->city,
        'state'                 => $faker->state,
        'country'               => $faker->country,
        'phone'                 => $faker->phoneNumber,
        'mobile'                => $faker->phoneNumber,
        'role'                  => \App\Models\User::BASIC_ROLE,
        'isActive'              => rand(0,1),
        'profileImage'          => $faker->imageUrl('100')
    ];
});