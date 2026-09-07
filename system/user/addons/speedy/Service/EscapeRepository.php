<?php

namespace BoldMinded\Speedy\Service;

class EscapeRepository
{
    /** @var string[] */
    private $escapes = [];

    /** @var string[] */
    private $tags = [];

    /**
     * @param string $data
     * @return string
     */
    public function create($data, $tag = null)
    {
        if ($tag !== null && array_key_exists($tag, $this->tags)) {
            $key = $this->tags[$tag];
        } else {
            $key = hash('md5', $data, false);

            $this->escapes[$key] = $data;
            if ($tag) {
                $this->tags[$tag] = $key;
            }
        }

        return '{!-- speedy:escape:' . $key . ' --}';
    }

    /**
     * @param string $marker
     * @return string|null
     */
    public function retrieve($marker)
    {
        if (preg_match('~^\{!-- speedy:escape:(\w+) --\}$~', $marker, $matches)) {
            if (array_key_exists($matches[1], $this->escapes)) {
                return $this->escapes[$matches[1]];
            }
        }

        return null;
    }
}
