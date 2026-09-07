<?php

namespace BoldMinded\DataGrab\datatypes\wordpress\Parsers;

use BoldMinded\DataGrab\DataTypes\StructuredArray;
use BoldMinded\DataGrab\datatypes\wordpress\ParseException;
use BoldMinded\DataGrab\datatypes\wordpress\PostCollection;
use BoldMinded\DataGrab\datatypes\wordpress\PostsRepository;
use BoldMinded\DataGrab\Dependency\Cake\Utility\Hash;
use SimpleXMLElement;

class SimpleXML
{
    private array $postCollection = [];
    private array $postMeta = [];
    private array $customFieldNames = [];
    private array $customFields = [];
    private array $postTypes = [];

    use StructuredArray;

    public function parseString(SimpleXMLElement $xml, string $postType): array
    {
        $authors = [];
        $categories = [];
        $tags = [];
        $terms = [];

        // halt if loading produces an error
        if (!$xml) {
            throw new ParseException('There was an error when reading this WXR file');
        }

        $wxr_version = $xml->xpath('/rss/channel/wp:wxr_version');
        if (!$wxr_version) {
            throw new ParseException('This does not appear to be a WXR file, missing/invalid WXR version number');
        }

        $wxr_version = trim($wxr_version[0]);
        // confirm that we are dealing with the correct file format
        if (!preg_match('/^\d+\.\d+$/', $wxr_version)) {
            throw new ParseException('This does not appear to be a WXR file, missing/invalid WXR version number');
        }

        $base_url = $xml->xpath('/rss/channel/wp:base_site_url');
        $base_url = trim(isset($base_url[0]) ? $base_url[0] : '');

        $base_blog_url = $xml->xpath('/rss/channel/wp:base_blog_url');
        if ($base_blog_url) {
            $base_blog_url = trim($base_blog_url[0]);
        } else {
            $base_blog_url = $base_url;
        }

        $namespaces = $xml->getDocNamespaces();
        if (!isset($namespaces['wp'])) {
            $namespaces['wp'] = 'http://wordpress.org/export/1.1/';
        }
        if (!isset($namespaces['excerpt'])) {
            $namespaces['excerpt'] = 'http://wordpress.org/export/1.1/excerpt/';
        }

        // grab authors
        foreach ($xml->xpath('/rss/channel/wp:author') as $author_arr) {
            $a = $author_arr->children($namespaces['wp']);
            $login = (string)$a->author_login;
            $authors[$login] = [
                'author_id' => (int)$a->author_id,
                'author_login' => $login,
                'author_email' => (string)$a->author_email,
                'author_display_name' => (string)$a->author_display_name,
                'author_first_name' => (string)$a->author_first_name,
                'author_last_name' => (string)$a->author_last_name,
            ];
        }

        // grab cats, tags and terms
        foreach ($xml->xpath('/rss/channel/wp:category') as $term_arr) {
            $t = $term_arr->children($namespaces['wp']);
            $category = [
                'term_id' => (int)$t->term_id,
                'category_nicename' => (string)$t->category_nicename,
                'category_parent' => (string)$t->category_parent,
                'cat_name' => (string)$t->cat_name,
                'category_description' => (string)$t->category_description,
            ];

            foreach ($t->termmeta as $meta) {
                $category['termmeta'][] = [
                    'key' => (string)$meta->meta_key,
                    'value' => (string)$meta->meta_value,
                ];
            }

            $categories[] = $category;
        }

        foreach ($xml->xpath('/rss/channel/wp:tag') as $term_arr) {
            $t = $term_arr->children($namespaces['wp']);
            $tag = [
                'term_id' => (int)$t->term_id,
                'tag_slug' => (string)$t->tag_slug,
                'tag_name' => (string)$t->tag_name,
                'tag_description' => (string)$t->tag_description,
            ];

            foreach ($t->termmeta as $meta) {
                $tag['termmeta'][] = [
                    'key' => (string)$meta->meta_key,
                    'value' => (string)$meta->meta_value,
                ];
            }

            $tags[] = $tag;
        }

        foreach ($xml->xpath('/rss/channel/wp:term') as $term_arr) {
            $t = $term_arr->children($namespaces['wp']);
            $term = [
                'term_id' => (int)$t->term_id,
                'term_taxonomy' => (string)$t->term_taxonomy,
                'slug' => (string)$t->term_slug,
                'term_parent' => (string)$t->term_parent,
                'term_name' => (string)$t->term_name,
                'term_description' => (string)$t->term_description,
            ];

            foreach ($t->termmeta as $meta) {
                $term['termmeta'][] = [
                    'key' => (string)$meta->meta_key,
                    'value' => (string)$meta->meta_value,
                ];
            }

            $terms[] = $term;
        }

        // grab posts
        foreach ($xml->channel->item as $item) {
            $post = [
                'post_title' => (string)$item->title,
                'guid' => (string)$item->guid,
            ];

            $dc = $item->children('http://purl.org/dc/elements/1.1/');
            $post['post_author'] = (string)$dc->creator;

            $content = $item->children('http://purl.org/rss/1.0/modules/content/');
            $excerpt = $item->children($namespaces['excerpt']);
            $post['post_content'] = (string)$content->encoded;
            $post['post_excerpt'] = (string)$excerpt->encoded;

            $wp = $item->children($namespaces['wp']);

            $post['post_id'] = (int)$wp->post_id;
            $post['post_date'] = (string)$wp->post_date;
            $post['post_date_gmt'] = (string)$wp->post_date_gmt;
            $post['comment_status'] = (string)$wp->comment_status;
            $post['ping_status'] = (string)$wp->ping_status;
            $post['post_name'] = (string)$wp->post_name;
            $post['status'] = (string)$wp->status;
            $post['post_parent'] = (int)$wp->post_parent;
            $post['menu_order'] = (int)$wp->menu_order;
            $post['post_type'] = (string)$wp->post_type;
            $post['post_password'] = (string)$wp->post_password;
            $post['is_sticky'] = (int)$wp->is_sticky;
            $post['post_meta'] = [];

            $this->postTypes[] = $post['post_type'];

            if (isset($wp->attachment_url)) {
                $post['attachment_url'] = (string)$wp->attachment_url;
            }

            foreach ($item->category as $c) {
                $att = $c->attributes();
                if (isset($att['nicename'])) {
                    $post['terms'][] = [
                        'name' => (string)$c,
                        'slug' => (string)$att['nicename'],
                        'domain' => (string)$att['domain'],
                    ];
                }
            }

            foreach ($wp->comment as $comment) {
                $meta = [];
                if (isset($comment->commentmeta)) {
                    foreach ($comment->commentmeta as $m) {
                        $meta[] = [
                            'key' => (string)$m->meta_key,
                            'value' => $this->cleanContent((string)$m->meta_value, (string)$m->meta_key),
                        ];
                    }
                }

                $post['comments'][] = [
                    'comment_id' => (int)$comment->comment_id,
                    'comment_author' => (string)$comment->comment_author,
                    'comment_author_email' => (string)$comment->comment_author_email,
                    'comment_author_IP' => (string)$comment->comment_author_IP,
                    'comment_author_url' => (string)$comment->comment_author_url,
                    'comment_date' => (string)$comment->comment_date,
                    'comment_date_gmt' => (string)$comment->comment_date_gmt,
                    'comment_content' => (string)$comment->comment_content,
                    'comment_approved' => (string)$comment->comment_approved,
                    'comment_type' => (string)$comment->comment_type,
                    'comment_parent' => (string)$comment->comment_parent,
                    'comment_user_id' => (int)$comment->comment_user_id,
                    'commentmeta' => $meta,
                ];
            }

            foreach ($wp->postmeta as $meta) {
                $this->postMeta[(int)$wp->post_id][] = [
                    'key' => (string)$meta->meta_key,
                    'value' => $this->cleanContent((string)$meta->meta_value, (string)$meta->meta_key),
                ];

                $post['post_meta'][] = [
                    'key' => (string)$meta->meta_key,
                    'value' => $this->cleanContent((string)$meta->meta_value, (string)$meta->meta_key),
                ];
            }

            $this->postCollection[$post['post_id']] = $post;
        }

        $postsRepository = new PostsRepository(
            posts: $this->postCollection,
            postMeta: $this->postMeta,
            customFields: $this->customFields,
        );

        $acfFields = $postsRepository->getPostsByType('^acf-field');
        $acfCollection = new PostCollection($acfFields);
        $acfNested = $acfCollection->createNestedCollection();

        $postsByType = $postsRepository->getPostsByType($postType);
        $postsByTypeCollection = new PostCollection($postsByType);

        $fieldPaths = $acfNested->buildPaths();
        $postsCollection = $postsByTypeCollection->getPosts();

        foreach ($postsCollection as &$post) {
            $post['custom_fields'] = $this->transformAcfMeta($post['post_meta'], $fieldPaths);
            $post['post_meta'] = $this->transformPostMeta($post['post_meta']);
        }

        return [
            'authors' => $authors,
            'post_types' => array_values(array_unique($this->postTypes)),
            'posts' => $postsCollection,
            'categories' => $categories,
            'tags' => $tags,
            'terms' => $terms,
            'base_url' => $base_url,
            'base_blog_url' => $base_blog_url,
            'version' => $wxr_version,
            'custom_fields' => $this->customFields,
            'custom_fields_names' => $this->customFieldNames,
        ];
    }

    private function transformPostMeta(array $postMeta): array
    {
        $result = [];
        $acfFieldKeys = [];

        // First pass: find ACF mapping keys (e.g. _hero_heading => field_abc123)
        foreach ($postMeta as $meta) {
            if (
                str_starts_with($meta['key'], '_') &&
                str_starts_with((string) $meta['value'], 'field_')
            ) {
                $acfFieldKeys[] = ltrim($meta['key'], '_');
            }
        }

        // Build result array, excluding ACF keys + their real fields
        foreach ($postMeta as $meta) {
            $key = $meta['key'];
            $value = $meta['value'];

            // Skip if this is an ACF mapping key (value starts with 'field_')
            if (
                str_starts_with($key, '_') &&
                str_starts_with((string) $value, 'field_')
            ) {
                continue;
            }

            // Skip if this key matches one of the known ACF field keys
            if (in_array($key, $acfFieldKeys, true)) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function transformAcfMeta(array $postMeta, array $fieldPaths): array
    {
        $fieldIdToPath = $fieldPaths;

        // Map meta key => value
        $valueMap = [];
        $keyMap = [];

        foreach ($postMeta as $meta) {
            $key = $meta['key'];
            $value = $meta['value'];

            if (str_starts_with($key, '_')) {
                $keyMap[$key] = $value;
            } else {
                $valueMap[$key] = $value;
            }
        }

        // Traverse and build structured array
        $result = [];

        foreach ($keyMap as $metaKey => $fieldId) {
            if (!isset($fieldIdToPath[$fieldId])) {
                continue;
            }

            $fieldName = substr($metaKey, 1); // remove leading "_"
            $value = $valueMap[$fieldName] ?? null;

            if (
                is_numeric($value)
                && array_key_exists($value, $this->postCollection)
                && $this->postCollection[$value]['post_type'] === 'attachment'
                && $this->postCollection[$value]['attachment_url'] !== ''
            ) {
                $value = $this->postCollection[$value]['attachment_url'];
            }

            if ($value === null || $value === '') {
                continue;
            }

            $path = $fieldIdToPath[$fieldId];
            $segments = explode('/', $path);
            $ref =& $result;

            foreach ($segments as $segment) {
                if (!isset($ref[$segment])) {
                    $ref[$segment] = [];
                }

                $ref =& $ref[$segment];
            }

            $isRepeater = $this->isRepeatingGroup($path, $fieldIdToPath);

            if ($isRepeater) {
                $ref[] = $value;
            } else {
                $ref = $value;
            }
        }

        return $result;
    }

    private function isRepeatingGroup(string $path, array $allPaths): bool
    {
        $segments = explode('/', $path);
        $parentPath = implode('/', array_slice($segments, 0, -1));

        $count = 0;
        foreach ($allPaths as $checkPath) {
            if (str_starts_with($checkPath, $parentPath . '/')) {
                $count++;
                if ($count > 1) return true;
            }
        }
        return false;
    }


    private function cleanContent(string $content, string $key = ''): string
    {
        // Call me maybe
        if ($key && $key === '_elementor_data') {
            //$config = HTMLPurifier_Config::createDefault();
            //$purifier = new HTMLPurifier($config);
            //$content = $purifier->purify($content);
            //$elementorData = json_decode($content, true);
        }

        return str_replace(["\n", "\t"], "", $content);
    }

}
