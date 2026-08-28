<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogComment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    use ApiResponse;

    /**
     * Get approved comments for a blog (top-level with nested replies).
     */
    public function index(string $slug): JsonResponse
    {
        $blog = Blog::where('slug', $slug)->where('is_published', true)->firstOrFail();

        $comments = BlogComment::with(['user', 'replies.user', 'replies.replies.user'])
            ->where('blog_id', $blog->id)
            ->whereNull('parent_id')
            ->where('is_approved', true)
            ->latest()
            ->get();

        return $this->success($comments);
    }

    /**
     * Store a new comment on a blog post.
     * Guests can comment with name/email; logged-in users use their auth.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $blog = Blog::where('slug', $slug)->where('is_published', true)->firstOrFail();

        // Always accept name/email as optional input
        $validated = $request->validate([
            'content'   => 'required|string|max:2000',
            'name'      => 'nullable|string|max:255',
            'email'     => 'nullable|email|max:255',
            'parent_id' => 'nullable|exists:blog_comments,id',
        ]);

        // Determine author: prefer auth user, fallback to provided name/email
        $authUser = $request->user();
        if ($authUser) {
            $validated['user_id'] = $authUser->id;
            $validated['name']    = $authUser->name;
            $validated['email']   = $authUser->email;
        } else {
            // Guest: must provide name and email
            if (empty($validated['name']) || empty($validated['email'])) {
                return $this->error('Name and email are required for guest comments.', 422, [
                    'name'  => empty($validated['name'])  ? ['The name field is required for guest comments.']  : null,
                    'email' => empty($validated['email']) ? ['The email field is required for guest comments.'] : null,
                ]);
            }
        }

        $validated['blog_id'] = $blog->id;

        $comment = BlogComment::create($validated);
        $comment->load('user');

        return $this->success($comment, 'Comment posted', 201);
    }

    /**
     * Delete a comment (owner or admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = BlogComment::findOrFail($id);
        $user = $request->user();

        // Only the comment owner or an admin can delete
        if ($comment->user_id !== $user->id && !in_array($user->role, ['admin', 'super_admin'])) {
            return $this->error('Unauthorized', 403);
        }

        $comment->delete();

        return $this->success(null, 'Comment deleted');
    }
}
