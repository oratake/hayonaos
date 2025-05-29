<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Locations;
use App\Models\User; // Userモデルをインポート
use Faker\Factory as Faker; // Fakerをインポート

class LocationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // 既存のユーザーを取得するか、なければ作成する
        // ここでは、テスト用に新しいユーザーを作成し、そのIDを使用します。
        // 実際の運用では、既存のユーザーIDを指定するか、適切なユーザー選択ロジックを実装してください。
        $user = User::factory()->create();
        $faker = Faker::create('ja_JP'); // 日本語のダミーデータを生成

        for ($i = 0; $i < 10; $i++) {
            Locations::create([
                'user_id' => $user->id,
                'name' => $faker->word . 'の場所', // 例: 「机の場所」
                'description' => $faker->realText(50), // 50文字程度のランダムな日本語テキスト
                'qr_code_url' => $faker->optional()->url, // 任意でURLを生成
            ]);
        }
    }
}