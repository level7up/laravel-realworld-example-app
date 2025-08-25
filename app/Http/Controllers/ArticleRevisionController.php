<?php
namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleRevision;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ArticleRevisionResource;
use App\Http\Resources\ArticleRevisionCollection;

class ArticleRevisionController extends Controller
{

    public function revisions(Article $article)
    {
        return new ArticleRevisionCollection($article->revisions);
    }
    public function revision(Article $article, ArticleRevision $articleRevision)
    {
        if ($articleRevision->article_id !== $article->id) {
            abort(404, 'Revision does not belong to this article.');
        }
        return new ArticleRevisionResource($articleRevision);
    }
    public function revert(Article $article, ArticleRevision $articleRevision)
    {
        if (auth()->id() !== $article->user_id) {
            abort(403, 'Unauthorized');
        }
        if ($articleRevision->article_id !== $article->id) {
            abort(404, 'Revision does not belong to this article.');
        }
        DB::transaction(function () use ($article, $articleRevision) {
            $article->update([
                'title' => $articleRevision->title,
                'slug' => $articleRevision->slug,
                'description' => $articleRevision->description,
                'body' => $articleRevision->body,
            ]);
        });

        return $this->articleResponse($article);
    }
}
