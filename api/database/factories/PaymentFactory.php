<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference' => 'JC_'.strtoupper(Str::random(32)),
            'checkout_id' => 'cs_'.Str::random(20),
            'plan' => 'monthly',
            'amount' => 4000,
            'currency' => 'NGN',
            'status' => 'pending',
        ];
    }
}
