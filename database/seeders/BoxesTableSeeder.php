<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Box;
use App\Models\User;
use Faker\Factory as Faker;

class BoxesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $user = User::factory()->create(); // Seeder実行ごとにUserが作られる点に注意

        $faker = Faker::create('ja_JP');

        for ($i = 0; $i < 10; $i++) {
            Box::create([
                'user_id' => $user->id,
                'name' => $faker->word . 'の箱',
                'description' => $faker->realText(50),
                'qr_code_url' => $faker->optional()->url,
            ]);
        }
    }
}