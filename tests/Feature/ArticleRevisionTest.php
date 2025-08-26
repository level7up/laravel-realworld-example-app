<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Article;
use App\Models\ArticleRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArticleRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_article_revisions(): void
    {

        $owner = User::factory()->create(['email' => 'admin@admin.com']);
        $article = Article::factory()->for($owner)->create([
            'title' => 'Initial Title',
            'description' => 'Initial description',
            'body' => 'Initial body',
        ]);
        $article->update(['title' => 'Title v2', 'body' => 'Body v2']);
        $article->update(['title' => 'Title v3', 'body' => 'Body v3']);
        $article->update(['title' => 'Title v4', 'body' => 'Body v4']);

        $response = $this->actingAs($owner)->get('api/articles/'.$article->slug.'/revisions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'revisions' => [
                    ['id', 'revision_number', 'slug', 'title', 'description', 'body', 'createdAt', 'updatedAt', 'author' => ['username', 'bio', 'image', 'following']],
                ],
                'revisionsCount',
            ]);
        $this->assertEquals(3, $response->json('revisionsCount'));
        $this->assertCount(3, $response->json('revisions'));
    }

    public function test_can_show_single_revision_for_article(): void
    {

        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $article = Article::factory()->for($owner)->create([
            'title' => 'Start',
            'description' => 'Desc',
            'body' => 'Body 1',
        ]);
        $article->update(['title' => 'Start v2', 'body' => 'Body 2']);
        $revision = ArticleRevision::where('article_id', $article->id)->firstOrFail();

        $response = $this->actingAs($owner)->get('api/articles/'.$article->slug.'/revisions/'.$revision->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'article_revision' => ['id', 'revision_number', 'slug', 'title', 'description', 'body', 'createdAt', 'updatedAt', 'author' => ['username', 'bio', 'image', 'following']],
            ]);
        $this->assertSame($revision->id, $response->json('article_revision.id'));
        $this->assertSame($owner->username, $response->json('article_revision.author.username'));
    }

    public function test_owner_can_revert_to_revision_and_observer_skips_on_revert(): void
    {
        $owner = User::factory()->create(['email' => 'owner2@example.com']);
        $article = Article::factory()->for($owner)->create([
            'title' => 'Version 1',
            'description' => 'Desc',
            'body' => 'Body V1',
        ]);
        $article->update(['title' => 'Version 2', 'body' => 'Body V2']);
        $revision = ArticleRevision::where('article_id', $article->id)->orderBy('revision_number')->firstOrFail();
        $countBefore = ArticleRevision::where('article_id', $article->id)->count();

        $response = $this->actingAs($owner)->post('api/articles/'.$article->slug.'/revisions/'.$revision->id.'/revert');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'article' => [
                    'slug', 'title', 'body', 'description', 'tagList', 'createdAt', 'updatedAt', 'favorited', 'favoritesCount', 'author' => ['username', 'bio', 'image', 'following']
                ],
            ]);
        $this->assertSame($revision->title, $response->json('article.title'));
        $this->assertSame($revision->body, $response->json('article.body'));

        $countAfter = ArticleRevision::where('article_id', $article->id)->count();
        $this->assertSame($countBefore, $countAfter);
    }

    public function test_unauthenticated_cannot_list_revisions(): void
    {
        $article = Article::factory()->create();

        $this->withHeaders(['Accept' => 'application/json'])
            ->get('api/articles/'.$article->slug.'/revisions')
            ->assertStatus(401);
    }

    public function test_unauthenticated_cannot_show_revision(): void
    {
        $article = Article::factory()->create();
        $article->update(['title' => 'Changed']);
        $revision = ArticleRevision::where('article_id', $article->id)->firstOrFail();

        $this->withHeaders(['Accept' => 'application/json'])
            ->get('api/articles/'.$article->slug.'/revisions/'.$revision->id)
            ->assertStatus(401);
    }

    public function test_unauthenticated_cannot_revert_revision(): void
    {
        $article = Article::factory()->create();
        $article->update(['title' => 'Changed']);
        $revision = ArticleRevision::where('article_id', $article->id)->firstOrFail();

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('api/articles/'.$article->slug.'/revisions/'.$revision->id.'/revert')
            ->assertStatus(401);
    }

    public function test_non_owner_cannot_revert_revision(): void
    {
        $owner = User::factory()->create(['email' => 'owner3@example.com']);
        $article = Article::factory()->for($owner)->create();
        $article->update(['title' => 'Changed']);
        $revision = ArticleRevision::where('article_id', $article->id)->firstOrFail();
        $otherUser = User::factory()->create(['email' => 'intruder@example.com']);

        $this->actingAs($otherUser)
            ->post('api/articles/'.$article->slug.'/revisions/'.$revision->id.'/revert')
            ->assertStatus(403);
    }

    public function test_mismatched_revision_returns_404(): void
    {
        $owner = User::factory()->create(['email' => 'owner4@example.com']);
        $articleA = Article::factory()->for($owner)->create();
        $articleB = Article::factory()->for($owner)->create();
        $articleA->update(['title' => 'A changed']);
        $revOfA = ArticleRevision::where('article_id', $articleA->id)->firstOrFail();

        $this->actingAs($owner)
            ->get('api/articles/'.$articleB->slug.'/revisions/'.$revOfA->id)
            ->assertStatus(404);

        $this->actingAs($owner)
            ->post('api/articles/'.$articleB->slug.'/revisions/'.$revOfA->id.'/revert')
            ->assertStatus(404);
    }

    public function test_observer_creates_revision_on_update(): void
    {
        $owner = User::factory()->create(['email' => 'observer@example.com']);
        $article = Article::factory()->for($owner)->create([
            'title' => 'Observer V1',
            'description' => 'Desc',
            'body' => 'Body V1',
        ]);

        $this->assertDatabaseCount('article_revisions', 0);
        $article->update(['title' => 'Observer V2', 'body' => 'Body V2']);

        $this->assertDatabaseHas('article_revisions', [
            'article_id' => $article->id,
            'user_id' => $owner->id,
            'title' => 'Observer V1',
            'body' => 'Body V1',
            'revision_number' => 1,
        ]);
    }
}
