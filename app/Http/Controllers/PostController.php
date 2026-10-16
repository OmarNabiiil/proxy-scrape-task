<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class PostController extends Controller
{
    /**
     * Show the profile for a given user.
     */
    public function index(IndexPostsRequest $request): AnonymousResourceCollection
    {
        $rows = $this->posts->all();


        return PostResource::collection($rows);
    }
}