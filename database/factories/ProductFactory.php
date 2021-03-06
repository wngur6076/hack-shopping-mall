<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => 'Example Band',
            'body' => 'with The Fake Openers',
            'poster_video_path' => 'https://youtu.be/nfQzseYn-ow',
            'file_link' => 'https://drive.google.com/file/d/1LYozTVCPd5HMAxn0Ypy0M1fu7I6b3GtW/view?usp=sharing',
            'user_id' => function () {
                return User::factory()->create()->id;
            },
        ];
    }
}
