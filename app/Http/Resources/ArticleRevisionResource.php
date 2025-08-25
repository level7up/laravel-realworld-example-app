<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ArticleRevisionResource extends JsonResource
{
    public static $wrap = 'article_revision';

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'body' => $this->body,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'author' => [
                'username' => $this->user->username,
                'bio' => $this->user->bio,
                'image' => $this->user->image,
                'following' => $this->user->followers->contains(auth()->id())
            ]
        ];
    }
}
