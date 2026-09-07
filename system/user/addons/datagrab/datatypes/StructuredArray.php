<?php

namespace BoldMinded\DataGrab\DataTypes;

trait StructuredArray
{
    /**
     * Create an array structure that should identically match that of what xmlToStructuredArray()
     * is doing. Then regardless of which import file type is chosen, the flat array should be the same.
     * Not going lie, got a little assist from ChatGPT on this one...
     */
    public function toStructuredArray(array $data, bool $isWordpress = false): array {
        return $this->transformPreservingTopLevel($data, $isWordpress);
    }

    private function transformPreservingTopLevel(array $data, bool $isWordpress = false): array {
        $output = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (!$isWordpress) {
                    $output[$key] = array_is_list($value)
                        ? array_map(fn($item) => $this->transformEntry($item), $value)
                        : $this->transformPreservingTopLevel($value);
                } else {
                    if (array_is_list($value)) {
                        $output[$key] = array_map(fn($item) => $this->transformEntry($item), $value);
                    } elseif ($this->isAssociativeGroup($value)) {
                        $output[$key] = $this->transformEntry($value);
                    } else {
                        $output[$key] = $this->transformPreservingTopLevel($value);
                    }
                }

            } else {
                $output[$key] = $value;
            }
        }

        return $output;
    }

    private function isAssociativeGroup(array $value): bool {
        // At least one string key and one nested array value (heuristic for grouping)
        foreach ($value as $v) {
            if (is_array($v)) return true;
        }

        // treat all associative arrays as groups
        return true;
    }

    private function transformEntry(array $entry): array {
        $result = [];

        foreach ($entry as $key => $value) {
            if (is_array($value)) {
                $structured = $this->transformGroup($key, $value);
                foreach ($structured as $block) {
                    $result[] = $block;
                }
            } else {
                $result[] = [$key => $value];
            }
        }

        return $result;
    }

    private function transformGroup(string $groupKey, array $groupValue): array {
        $children = [];

        foreach ($groupValue as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value) && $this->allScalars($value)) {
                    foreach ($value as $v) {
                        $children[] = [$key => $v];
                    }
                } elseif (array_is_list($value)) {
                    foreach ($value as $child) {
                        $transformed = $this->transformGroup($key, $child);
                        if (!empty($transformed)) {
                            foreach ($transformed as $t) {
                                $children[] = $t;
                            }
                        }
                    }
                } else {
                    $transformed = $this->transformGroup($key, $value);
                    if (!empty($transformed)) {
                        foreach ($transformed as $t) {
                            $children[] = $t;
                        }
                    }
                }
            } else {
                $children[] = [$key => $value];
            }
        }

        // Optimize: Only include __parent__ node if the group actually has multiple keys (not just re-wrapped already structured children)
        $flattenedKeys = array_map(function($item) {
            return array_key_first($item);
        }, $children);
        $numRealChildren = count(array_filter($flattenedKeys, fn($k) => !str_ends_with($k, '/__parent__')));

        //if ($numRealChildren === 0) {
        //    return [];
        //}

        // Treat associative group with scalar fields as 1 child block
        if ($numRealChildren === 0 && !empty($children)) {
            $numRealChildren = 1;
        }

        return [
            [$groupKey . '/__parent__' => '[includes ' . $numRealChildren . ' ' . ($numRealChildren === 1 ? 'child' : 'children') . ']'],
            [$groupKey => $children]
        ];
    }

    private function allScalars(array $arr): bool {
        foreach ($arr as $v) {
            if (is_array($v)) return false;
        }
        return true;
    }
}
