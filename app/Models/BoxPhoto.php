<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BoxPhoto extends Model
{
    use HasFactory;

    protected $table = 'tbl_box_photos';

    protected $fillable = [
        'box_id',
        'file_path',
        'caption',
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
     * この写真が属するBOXを取得
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}