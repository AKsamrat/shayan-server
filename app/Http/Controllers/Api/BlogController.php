<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Blog::with('author')
            ->where('is_published', true)
            ->latest();

        $result = $this->paginated($query);
        return $this->success($result);
    }

    public function show(string $slug): JsonResponse
    {
        $blog = Blog::with('author')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $blog->increment('views_count');

        return $this->success($blog);
    }
}
