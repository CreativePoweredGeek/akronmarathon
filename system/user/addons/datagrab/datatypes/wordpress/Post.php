<?php

namespace BoldMinded\DataGrab\datatypes\wordpress;

class Post
{
    public function __construct(
        public int $postId,
        public int $parentId,
        public string $slug,
        public string $title,
        public string $content,
        public string $excerpt,
        public string $date,
        public string $status,
        public string $type,
        public array $meta = [],
        public int $order = 0,
        public PostCollection $children = new PostCollection([]),
    ) {}

    /** @var Post[] */
    public function setChildren(PostCollection $children): Post
    {
        $this->children = $children;

        return $this;
    }

    public function addChild(Post $child): Post
    {
        $this->children->addPost($child);

        return $this;
    }

    public function getChildren(): array
    {
        return $this->children->getPosts();
    }

    public function hasChildren(): bool
    {
        return $this->children->isEmpty() !== true;
    }

    public function asArray(): array
    {
        return [
            'post_id' => $this->postId,
            'parent_id' => $this->parentId,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'date' => $this->date,
            'status' => $this->status,
            'type' => $this->type,
            'meta' => $this->meta,
            'children' => $this->children->asArray(),
        ];
    }

    public function getPath(): string
    {
        return $this->excerpt;
    }
}
