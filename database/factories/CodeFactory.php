<?php

namespace Database\Factories;

use App\Models\Code;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class CodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Code::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'product_id' => function () {
                return Product::factory()->create()->id;
            },
            'serial_number' => 'test',
            'price' => 1000
        ];
    }

    public function period($period)
    {
        return $this->state([
            'period' => $period
        ]);
    }
}
