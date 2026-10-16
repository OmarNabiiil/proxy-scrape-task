<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Table('posts')]
class Post extends Model
{
    
    /**
     * Get all of the tags for the post.
     */
    public function pictures(): MorphMany
    {
        return $this->morphMany(Picture::class, 'picturable');
    }
}