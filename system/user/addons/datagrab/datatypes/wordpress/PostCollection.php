<?php

namespace BoldMinded\DataGrab\datatypes\wordpress;

class PostCollection
{
    public function __construct(
        private array $posts = [],
    ) {}

    /** @return PostCollection[]  */
    public function getPosts(): array
    {
        return $this->posts;
    }

    public function addPost(Post $post): PostCollection
    {
        $this->posts[] = $post;

        return $this;
    }

    public function isEmpty(): bool
    {
        return count($this->posts) === 0;
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->posts);
    }

    public function asArray(): array
    {
        return array_map(static fn ($post) => $post->asArray(), $this->posts);
    }

    public function buildPaths(): array
    {
        $paths = [];

        // Loop through top-level posts
        foreach ($this->posts as $post) {
            $this->constructPath($post, '', $paths);
        }

        return $paths;
    }

    private function constructPath(Post $post, string $currentPath = '', array &$paths = []): array
    {
        // Append the current post's excerpt to the path if it exists
        if (!empty($post->excerpt)) {
            $currentPath .= ($currentPath ? '/' : '') . $post->excerpt;
        }

        // Check if the post has children
        if ($post->hasChildren()) {
            $children = $post->getChildren();
            foreach ($children as $child) {
                // Recursively process each child
                $this->constructPath($child, $currentPath, $paths);
            }
        } else {
            // If this is the last child (leaf node), append the slug and add the full path to the paths array
            if (!empty($post->slug)) {
                $paths[$post->slug] = $currentPath;
            }
        }

        return $paths;
    }

    public function createNestedCollection(int $parentPostId = 0): PostCollection
    {
        $rootLevel = $this->findPostParent($parentPostId);
        $collection = new static();

        foreach ($rootLevel as $rootPost) {
            $post = new Post(
                postId: (int) $rootPost['post_id'],
                parentId: (int) $rootPost['post_parent'],
                slug: $rootPost['post_name'],
                title: $rootPost['post_title'],
                content: $rootPost['post_content'],
                excerpt: $rootPost['post_excerpt'],
                date: $rootPost['post_date'],
                status: $rootPost['status'],
                type: $rootPost['post_type'],
                meta: $rootPost['post_meta'] ?? [],
                order: $rootPost['menu_order'],
            );

            $children = $this->findChildPosts($post);

            if (!$children->isEmpty()) {
                $post->setChildren($children);
            }

            $collection->addPost($post);
        }

        return $collection;
    }

    private function findChildPosts(Post $post): PostCollection
    {
        $children = $this->findPostParent($post->postId);
        $collection = new PostCollection();

        foreach ($children as $child) {
            $childPost = new Post(
                postId: (int) $child['post_id'],
                parentId: (int) $child['post_parent'],
                slug: $child['post_name'],
                title: $child['post_title'],
                content: $child['post_content'],
                excerpt: $child['post_excerpt'],
                date: $child['post_date'],
                status: $child['status'],
                type: $child['post_type'],
                meta: $child['post_meta'] ?? [],
                order: $child['menu_order'],
            );

            $grandChildren = $this->findChildPosts($childPost);
            $childPost->setChildren($grandChildren);
            $collection->addPost($childPost);
        }

        return $collection;
    }

    private function findPostParent(int $parentPostId): array
    {
        return array_filter($this->posts, function ($field) use ($parentPostId) {
            return $field['post_parent'] === $parentPostId;
        });
    }
}
