<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfficeFactory extends Factory {

    protected $model = Office::class;

    public function definition()
    {
        return [
            'user_id'          => User::factory(),
            'title'            => $this->faker->city,
            'description'      => $this->faker->paragraph,
            'lat'              => $this->faker->latitude,
            'lng'              => $this->faker->longitude,
            'address_line1'    => $this->faker->address,
            'approval_status'  => Office::APPROVAL_APPROVED,
            'hidden'           => false,
            'price_per_day'    => $this->faker->randomElement([1_000, 2_000, 4_000]),
            'monthly_discount' => 0,
        ];
    }
}
