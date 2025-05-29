<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxPhoto extends Model
{
    use HasFactory;

    protected $table = 'box_photos';

    protected $fillable = [
        'box_id',
        'photo_url',
        'description',
    ];

    /**
     * この写真が属するBOXを取得
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}