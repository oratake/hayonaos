<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Box extends Model
{
    use HasFactory;

    protected $table = 'tbl_box';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'qr_code_url',
    ];

    /**
     * モデルの起動メソッド (UUID生成)
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

    /**
     * このBOXに紐づく写真を取得
     */
    public function photos(): HasMany
    {
        return $this->hasMany(BoxPhoto::class);
    }
}