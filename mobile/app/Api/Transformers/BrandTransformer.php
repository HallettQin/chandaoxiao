<?php

namespace App\Api\Transformers;

use League\Fractal\TransformerAbstract;

class BrandTransformer extends TransformerAbstract
{
    public function transform(array $map)
    {
        return [
           'id' => $map['article_id'],
           'title' => $map['title'],
        ];
    }
}
