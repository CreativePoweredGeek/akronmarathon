<?php

namespace TucsonSentinel\TsFieldersChoice\Service;

class CategoryChoiceLists
{
    /**
     * @param array<string, mixed> $settings Fieldtype settings (fc_* keys).
     * @return int[]
     */
    public static function categoryGroupIdsForSettings(array $settings)
    {
        $mode = isset($settings['fc_source_mode']) ? (string) $settings['fc_source_mode'] : 'category_group';

        if ($mode === 'channel') {
            $channelId = (int) ($settings['fc_channel_id'] ?? 0);

            if ($channelId <= 0) {
                return [];
            }

            $channel = ee('Model')->get('Channel', $channelId)->with('CategoryGroups')->first();

            if ($channel === null || $channel->CategoryGroups === null) {
                return [];
            }

            $ids = $channel->CategoryGroups->pluck('group_id');

            return array_values(array_unique(array_map('intval', $ids)));
        }

        $groupId = (int) ($settings['fc_category_group_id'] ?? 0);

        return $groupId > 0 ? [$groupId] : [];
    }

    /**
     * Dropdown choices: cat_id => indented label.
     *
     * @param array<string, mixed> $settings
     * @param int|string|null      $selectedCatId
     * @return array<int|string, string>
     */
    public static function choicesForSettings(array $settings, $selectedCatId = null)
    {
        $groupIds = self::categoryGroupIdsForSettings($settings);

        if ($groupIds === []) {
            return [];
        }

        $choices = [];

        foreach ($groupIds as $groupId) {
            $group = ee('Model')->get('CategoryGroup', (int) $groupId)->first();

            if ($group === null) {
                continue;
            }

            $tree = $group->getCategoryTree(self::categoryTree());

            foreach ($tree->children() as $categoryNode) {
                self::appendCategoryNodeChoices($categoryNode, $choices);
            }
        }

        if ($choices === []) {
            return [];
        }

        $selectedCatId = (int) $selectedCatId;

        if ($selectedCatId > 0 && ! isset($choices[$selectedCatId])) {
            $orphan = self::categoryRow($selectedCatId);

            if ($orphan !== null) {
                $choices[$selectedCatId] = self::orphanLabel($orphan);
            } else {
                $choices[$selectedCatId] = lang('fc_orphan_missing') . ' #' . $selectedCatId;
            }
        }

        return $choices;
    }

    /**
     * EE_Tree is not always on ee()->tree (e.g. publish); load the legacy library directly.
     */
    private static function categoryTree()
    {
        if (! class_exists('EE_Tree', false)) {
            require_once SYSPATH . 'ee/legacy/libraries/datastructures/Tree.php';
        }

        return new \EE_Tree();
    }

    /**
     * Match CP category filters: "-- " per depth level (see EntryListing::setCategoryOptions).
     *
     * @param \EE_TreeNode               $category Tree node from CategoryGroup::getCategoryTree()
     * @param array<int|string, string> $choices
     */
    private static function appendCategoryNodeChoices($category, array &$choices)
    {
        $catId = (int) $category->data->cat_id;
        $depth = (int) $category->depth();
        $prefix = $depth > 1 ? str_repeat('-- ', $depth - 1) : '';

        $choices[$catId] = $prefix . (string) $category->data->cat_name;

        foreach ($category->children() as $child) {
            self::appendCategoryNodeChoices($child, $choices);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function categoryRow($catId)
    {
        $catId = (int) $catId;

        if ($catId <= 0) {
            return null;
        }

        $cat = ee('Model')->get('Category', $catId)->first();

        if ($cat === null) {
            return null;
        }

        return [
            'cat_id'         => (int) $cat->cat_id,
            'cat_name'       => (string) $cat->cat_name,
            'cat_url_title'  => (string) $cat->cat_url_title,
            'group_id'       => (int) $cat->group_id,
            'parent_id'      => (int) $cat->parent_id,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function orphanLabel(array $row)
    {
        return lang('fc_orphan_stale') . ': ' . $row['cat_name'] . ' (#' . $row['cat_id'] . ')';
    }

    /**
     * @param array<string, mixed> $settings
     * @param int|string           $catId
     */
    public static function isAllowedCategoryId(array $settings, $catId)
    {
        $catId = (int) $catId;

        if ($catId <= 0) {
            return false;
        }

        $choices = self::choicesForSettings($settings, null);

        if (isset($choices[$catId])) {
            return true;
        }

        $row = self::categoryRow($catId);

        if ($row === null) {
            return false;
        }

        $allowedGroups = self::categoryGroupIdsForSettings($settings);

        return in_array((int) $row['group_id'], $allowedGroups, true);
    }

    /**
     * @return array<int|string, string>
     */
    public static function channelChoices()
    {
        $siteId  = (int) ee()->config->item('site_id');
        $choices = ['' => '—'];

        $channels = ee('Model')->get('Channel')
            ->filter('site_id', $siteId)
            ->order('channel_title', 'asc')
            ->all();

        foreach ($channels as $channel) {
            $choices[(int) $channel->getId()] = (string) $channel->channel_title;
        }

        return $choices;
    }

    /**
     * @return array<int|string, string>
     */
    public static function categoryGroupChoices()
    {
        $siteId  = (int) ee()->config->item('site_id');
        $choices = ['' => '—'];

        $groups = ee('Model')->get('CategoryGroup')
            ->filter('site_id', $siteId)
            ->order('group_name', 'asc')
            ->all();

        foreach ($groups as $group) {
            $choices[(int) $group->getId()] = (string) $group->group_name;
        }

        return $choices;
    }
}
