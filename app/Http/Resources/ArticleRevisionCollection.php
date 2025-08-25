<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ArticleRevisionCollection extends ResourceCollection
{
    public static $wrap = '';

    public function toArray($request): array
    {
        return [
            'revisions' => $this->collection,
            'revisionsCount' => $this->count()
        ];
    }
}
