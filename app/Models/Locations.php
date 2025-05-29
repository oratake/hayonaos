<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Locations extends Model
{
    protected $table = 'tbl_locations';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'qr_code_url',
    ];

    /**
     * モデルの起動メソッド
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function photos()
    {
        return $this->hasMany(LocationPhotos::class, 'location_id', 'id');
    }
}
