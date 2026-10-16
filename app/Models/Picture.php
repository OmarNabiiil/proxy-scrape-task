<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
 
class Picture extends Model
{
    /**
     * Get all of the posts that are assigned this tag.
     */
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'picturable');
    }
 
    /**
     * Get all of the videos that are assigned this tag.
     */
    public function comments(): MorphToMany
    {
        return $this->morphedByMany(Video::class, 'picturable');
    }
}