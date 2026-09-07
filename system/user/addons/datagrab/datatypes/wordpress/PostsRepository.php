<?php

namespace BoldMinded\DataGrab\datatypes\wordpress;

class PostsRepository
{
    private array $nestedPosts = [];
    private array $allPosts = [];

    public function __construct(
        private array $posts = [],
        private array $postMeta = [],
        private array $customFields = [],
    ) {
        $this->allPosts = $posts;
    }

    public function getPosts(): array
    {
        return $this->posts;
    }

    public function findRelatedPostMetaValueByKey(int $postId, string $fieldId): array
    {
        $metaCollection = $this->findPostMetaById($postId);
        $customFieldName = $this->customFields[$fieldId]['name'];

        if (count($metaCollection) === 0) {
            return [
                'fieldName' => $customFieldName,
                'fieldValues' => [],
                'parentName' => '',
                'parentFieldId' => '',
            ];
        }

        $filteredByValue = array_filter($metaCollection, function ($meta) use ($fieldId) {
            return $meta['value'] === $fieldId;
        });

        $keys = array_column($filteredByValue, 'key');

        $keysCleaned = array_map(function ($key) {
            return substr($key, 0, 1) === '_' ? substr($key, 1) : $key;
        }, $keys);

        $filteredByKey = array_filter($metaCollection, function ($meta) use ($keysCleaned) {
            return in_array($meta['key'], $keysCleaned);
        });

        $fields = [];

        foreach ($filteredByKey as $metaPostId => $meta) {
            $fields[] = [
                'key' => $meta['key'],
                'value' => $meta['value'],
                'fieldId' => $fieldId,
                'postId' => $metaPostId,
            ];
        }

        $parent = $this->findParentField($fieldId);
        $parentName = '';
        $parentFieldId = '';
        $parentPostId = 0;

        if (count($parent) === 1) {
            $parentFieldId = key($parent);
            $parent = array_values($parent);
            $parentName = $parent[0]['name'] ?? '';
            $parentPostId = $parent[0]['post_id'] ?? '';
        }

        // @todo move this to a separate function and handle special cases
        foreach ($fields as &$field) {
            if (!ctype_digit($field['value'])) {
                continue;
            }

            $int = (int) $field['value'];

            if (
                isset($this->posts[$int]) &&
                $this->posts[$int]['post_type'] === 'attachment'
            ) {
                $field['value'] = $this->posts[$int]['attachment_url'];
            }
        }

        return [
            'fieldName' => $customFieldName,
            'fieldValues' => $fields,
            'parentName' => $parentName,
            'parentFieldId' => $parentFieldId,
            'parentPostId' => $parentPostId,
        ];
    }

    public function findPostById(int $id): array
    {
        return $this->allPosts[$id] ?? [];
    }

    public function findPostMetaById(int $id): array
    {
        return $this->postMeta[$id] ?? [];
    }

    public function findParentField(string $fieldId): array
    {
        $currentField = $this->customFields[$fieldId];

        return array_filter($this->customFields, function ($field) use ($currentField) {
            return $field['post_id'] === $currentField['parent'];
        });
    }

    private function collectMeta(int $postId): array
    {
        $allMeta = $this->postMeta[$postId] ?? [];
        $collection = [];

        foreach ($allMeta as $meta) {
            $key = $meta['key'] ?? '';
            $value = $meta['value'] ?? '';

            if (substr($key, 0, 1) === '_') {
                continue;
            }

            if (preg_match('/^(.*?)_(\d+)_(.*?)$/', $key, $matches)) {
                //$newKey = sprintf('%s_%s', $matches[1], $matches[3]);
                $newKey = $matches[1];
                $index = (int) $matches[2];
                $subKey = $matches[3];

                if (ctype_digit($value)) {
                    $relatedPost = $this->findPostById((int) $value);

                    if ($relatedPost && $relatedPost['post_type'] === 'attachment') {
                        $value = $relatedPost['attachment_url'];
                    }
                }

                if (!isset($collection[$newKey][$index])) {
                    $collection[$newKey][$index] = [];
                }

                $collection[$newKey][$index][$subKey] = $value;
            } elseif (!array_key_exists($key, $collection)) {
                $collection[$key][] = $value;
            }
        }

        return $collection;
    }

    public function getPostsByType(string $name): array
    {
        return array_filter($this->posts, function ($post) use ($name) {
            if (substr($name, 0, 1) === '^') {
                return preg_match('/'. $name .'/', $post['post_type']);
            }

            return $post['post_type'] === $name;
        });
    }
}
