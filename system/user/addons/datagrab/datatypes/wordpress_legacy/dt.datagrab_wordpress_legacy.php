<?php

use BoldMinded\DataGrab\DataTypes\AbstractDataTypeLegacy;

/**
 * DataGrab WordPress import class
 *
 * Allows WordPress imports
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_wordpress_legacy extends AbstractDataTypeLegacy
{
    public $type = 'Wordpress';

    public $datatype_info = [
        'name' => 'WordPress (Legacy)',
        'version' => '1.0',
        'description' => 'Import data from a WordPress export file',
        'allow_comments' => true
    ];

    public $settings = [
        'filename' => '',
        'post_type' => 'post',
    ];

    public $config_defaults = [
        'title' => 'title',
        'date' => 'pubDate',
        'import_comments' => 'y',
        'comment_author' => 'wp:comment/wp:comment_author',
        'comment_email' => 'wp:comment/wp:comment_author_email',
        'comment_url' => 'wp:comment/wp:comment_author_url',
        'comment_date' => 'wp:comment/wp:comment_date',
        'comment_body' => 'wp:comment/wp:comment_content',
        'cat_field' => 'categories',
        'author_field' => 'dc:creator',
        'status' => 'wp:status',
    ];

    /*
       Map WP statuses to EE ones. This is a temporary fix until I can
       add a more friendly user interface

       Alter this array to suit your requirements - you can use your own
       custom statuses here
    */
    public $statuses = [
        'publish' => 'Open',
        'draft' => 'Closed',
        'auto-draft' => 'Closed',
        'pending' => 'Closed',
        'inherit' => 'Closed'
    ];

    public $path = '/rss/channel/item';

    public $items;

    public array $postTypes = [];

    /**
     * @param array $values
     * @return array[]
     */
    public function settings_form(array $values = []): array
    {
        return [
            [
                'title' => 'Filename or URL',
                'desc' => lang('datagrab_filename_instructions'),
                'fields' => [
                    'filename' => [
                        'required' => true,
                        'type' => 'text',
                        'value' => $this->get_value($values, 'filename') ?: '{base_url}/my-file.xml',
                    ]
                ]
            ],
            [
                'title' => 'Post Type',
                'desc' => 'Choose which post type to import. If you\'re importing a basic blog it will most likely be 
                    <code>post</code>. You can only import one post type at a time. If you have additional custom
                    post types you will need to create another import using a different Channel. If you are wanting
                    to import the <code>attachment</code> post type you will probably want to choose <code>File</code>
                    in the Import type above, then choose a directory to import the files to.',
                'fields' => [
                    'post_type' => [
                        'required' => true,
                        'type' => 'text',
                        'value' => $this->get_value($values, 'post_type') ?: 'post',
                    ]
                ]
            ],
        ];
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function fetch(string $data = '')
    {
        try {
            ini_set('pcre.backtrack_limit','250000');
            $xml = $this->curlFetch($this->getFilename());
        } catch (Exception $exception) {
            return -1;
        }

        if($xml === false) {
            $this->addError('Cannot open file/url: ' . $this->getFilename());
            return -1;
        }

        $this->items = array();
        $this->items = $this->_fetch_xml( $xml );

        if ( $this->items == "" ) {
            $this->addError('Please check the path is correct: ' . $this->path);
            return -1;
        }
    }

    public function next()
    {
        // PHP 8.1 change
        if (!is_array($this->items)) {
            $this->items = (array) $this->items;
        }

        $item = current( $this->items );
        next( $this->items );

        return $item;
    }

    public function fetchPostTypes(): array
    {
        return array_unique(array_values($this->postTypes));
    }

    public function fetch_columns(): array
    {
        try {
            $this->fetch();
            $columns = $this->next();

            $titles = [
                'title' => 'Title',
                'pubDate' => 'Published Date',
                'link' => 'Link',
                'dc:creator' => 'Creator',
                'guid' => 'Guid',
                'description' => 'Description',
                'content:encoded' => 'Content',
                'wp:status' => 'Post Status',
                'wp:attachment_url' => 'Attachment URL',

                'wp:comment/wp:comment_author' => 'Comment Author',
                'wp:comment/wp:comment_author_email' => 'Comment Author Email',
                'wp:comment/wp:comment_author_url' => 'Comment Author URL',
                'wp:comment/wp:comment_date' => 'Comment Date',
                'wp:comment/wp:comment_content' => 'Comment Content',

                'categories' => 'Categories',
                'tags' => 'Tags',

                //'wp:term' => 'Term',
                'wp:post_type' => 'Post type',

                'wp:postmeta/wp:meta_key' => 'Meta Key',
                'wp:postmeta/wp:meta_value' => 'Meta Value',
            ];

            foreach ($titles as $idx => $title) {
                if (isset($columns[$idx])) {
                    $eg = $columns[$idx];
                    if (strlen($eg) > 32) {
                        $eg = substr(htmlspecialchars($eg), 0, 32) . "...";
                    }
                    if ($eg != "") {
                        $titles[$idx] .= " - eg, " . $eg;
                    }
                }
            }

            return $titles;
        } catch (Error $error) {
            $this->addError($error->getMessage());
        }

        return [];
    }

    private function _fetch_xml($xml)
    {
        $items = [];

        $item_array = $this->_fetch_tags($xml, "item");

        if (preg_last_error() !== PREG_NO_ERROR) {
            $this->addError(sprintf('XML Parsing Error: %s', preg_last_error_msg()));
        }

        foreach ($item_array as $i) {

            $item = array();

            // Fetch post type
            $item["wp:post_type"] = $this->_fetch_tags($i, "wp:post_type");
            $this->postTypes[] = $item["wp:post_type"];

            // We could filter the results by post type, or just add it as
            // an available field...

            if (
                isset($this->settings['post_type']) &&
                $this->settings['post_type'] !== $item["wp:post_type"]
            ) {
                continue;
            }

            // Fetch basic data
            $item["title"] = $this->_fetch_tags($i, "title");
            $item["pubDate"] = $this->_fetch_tags($i, "pubDate");
            $item["dc:creator"] = $this->_fetch_tags($i, "dc:creator");
            $item["link"] = $this->_fetch_tags($i, "link");
            $item["guid"] = $this->_fetch_tags($i, "guid");
            $item["description"] = $this->_fetch_tags($i, "description");
            $item["content:encoded"] = $this->_fetch_tags($i, "content:encoded");

            // Fetch and convert status
            $item["wp:status"] = $this->_fetch_tags($i, "wp:status");
            if (isset($this->statuses[$item["wp:status"]])) {
                $item["wp:status"] = $this->statuses[$item["wp:status"]];
            }

            $item['wp:attachment_url'] = $this->_fetch_tags($i, "wp:attachment_url");

            // Fetch comments
            $comments = $this->_fetch_pair('wp:comment', $i);

            $item["wp:comment#"] = count($comments);
            $item["wp:comment/wp:comment_content#"] = count($comments);
            $count = 0;
            foreach ($comments as $comment) {
                $count++;
                $prefix = "";
                if ($count > 1) {
                    $prefix = "#" . $count;
                }
                $item["wp:comment/wp:comment_author{$prefix}"] = $this->_fetch_tags($comment, "wp:comment_author");
                $item["wp:comment/wp:comment_author_email{$prefix}"] = $this->_fetch_tags($comment, "wp:comment_author_email");
                $item["wp:comment/wp:comment_author_url{$prefix}"] = $this->_fetch_tags($comment, "wp:comment_author_url");
                $item["wp:comment/wp:comment_date{$prefix}"] = $this->_fetch_tags($comment, "wp:comment_date");
                $item["wp:comment/wp:comment_content{$prefix}"] = $this->_fetch_tags($comment, "wp:comment_content");
            }

            // Fetch categories
            $categories = $this->_fetch_tags($i, "category", 'domain="category"');
            if (is_array($categories)) {
                $categories = array_unique($categories);
                foreach ($categories as $idx => $category) {
                    $categories[$idx] = $this->_remove_cdata($category);
                }
                $item["categories"] = implode(", ", $categories);
            } //tim addes to include post with single categories
            else {
                $item["categories"] = $this->_remove_cdata($categories);
            }

            // Fetch tags
            $categories = $this->_fetch_tags($i, "category", 'domain="tag"');
            if (is_array($categories)) {
                $categories = array_unique($categories);
                foreach ($categories as $idx => $category) {
                    $categories[$idx] = $this->_remove_cdata($category);
                }
                $item["tags"] = implode(", ", $categories);
            } //tim addes to include post with single tags
            else {
                $item["tags"] = $this->_remove_cdata($categories);
            }

            $metaData = $this->_fetch_pair('wp:postmeta', $i);
            $metaCount = 0;
            foreach ($metaData as $metaDatum) {
                $metaCount++;
                $prefix = '';
                if ($metaCount > 1) {
                    $prefix = '#' . $metaCount;
                }

                $key = $this->_fetch_tags($metaDatum, "wp:meta_key");
                $value = $this->_fetch_tags($metaDatum, "wp:meta_value");

                $item['wp:metadata/' . $key . $prefix] = $value;
            }

            $items[] = $item;

        }

        return $items;
    }

    /* Private functions */

    function _fetch_tags($xml, $tag, $attr = "")
    {
        $reg = "|<$tag.*?>(.*?)</$tag>|is";
        if ($attr != "") {
            $reg = "|<$tag $attr.*?>(.*?)</$tag>|is";
        }
        $count = preg_match_all($reg, $xml, $matches);
        if ($count == 0) {
            return '';
        }
        if ($count == 1) {
            $str = $matches[1][0];
            $str = $this->_remove_cdata($str);
            return $str;
        }
        return $matches[1];
    }

    function _remove_cdata($str)
    {
        $str = preg_replace('#^<!\[CDATA\[(.*)\]\]>$#s', '$1', $str);
        return $str;
    }

    function _fetch_pair(string $name, string $xml)
    {
        $count = preg_match_all('#<' . $name . '>(.*?)</' . $name . '>#is', $xml, $matches);

        if ($count == 0) {
            return array();
        }
        return $matches[1];
    }

    function _fsockopen_fetch($url)
    {
        $target = parse_url($url);

        $data = '';

        $fp = fsockopen($target['host'], 80, $error_num, $error_str, 8);

        if (is_resource($fp)) {
            fputs($fp, "GET {$url} HTTP/1.0\r\n");
            fputs($fp, "Host: {$target['host']}\r\n");
            fputs($fp, "User-Agent: EE/xmlgrab PHP/" . phpversion() . "\r\n\r\n");

            $headers = TRUE;

            while (!feof($fp)) {
                $line = fgets($fp, 4096);

                if ($headers === FALSE) {
                    $data .= $line;
                } elseif (trim($line) == '') {
                    $headers = FALSE;
                }
            }

            fclose($fp);
        }

        return $data;
    }
}
