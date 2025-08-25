<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\ArticleRevision;

class ArticleObserver
{
    /**
     * Handle the Article "created" event.
     */
    public function created(Article $article): void
    {
        //
    }

    /**
     * Handle the Article "updated" event.
     */
    public function updated(Article $article): void
    {
        if (request()->routeIs('articles.revisions.revert')) {
            return;
        }

        $original = $article->getOriginal();
        ArticleRevision::create([
            'article_id' => $original['id'],
            'user_id' => $original['user_id'],
            'slug'=> $original['slug'],
            'title'=> $original['title'],
            'body'=> $original['body'],
            'description'=> $original['description'],
            'revision_number' => ArticleRevision::where('article_id', $original['id'])->count() + 1,
        ]);
    }

    /**
     * Handle the Article "deleted" event.
     */
    public function deleted(Article $article): void
    {
        //
    }

    /**
     * Handle the Article "restored" event.
     */
    public function restored(Article $article): void
    {
        //
    }

    /**
     * Handle the Article "force deleted" event.
     */
    public function forceDeleted(Article $article): void
    {
        //
    }
}
