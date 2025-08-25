<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ArticleRevision extends Model
{
    use HasFactory;

    protected $fillable = ['article_id', 'title', 'slug', 'description', 'body', 'revision_number', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
