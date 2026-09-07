<?php

namespace BoldMinded\DataGrab\Service\ContentType;

use BoldMinded\DataGrab\Queue\Jobs\DeleteMember;
use BoldMinded\DataGrab\Queue\Jobs\ImportMember;

class MemberContentType implements ContentTypeInterface
{
    /**
     * EE's built in Banned role. Members are never hard deleted by an import,
     * they are moved into this role instead.
     */
    public const BANNED_ROLE_ID = 2;

    /**
     * Never managed by an import, so a misconfigured import can't lock out an admin.
     */
    public const SUPER_ADMIN_ROLE_ID = 1;

    public function getName(): string
    {
        return 'member';
    }

    public function getImportJobClass(): string
    {
        return ImportMember::class;
    }

    public function getDeleteJobClass(): string
    {
        return DeleteMember::class;
    }

    public function getCustomFieldPrefix(): string
    {
        return 'm_field_id_';
    }

    public function getFieldModelName(): string
    {
        return 'MemberField';
    }

    /**
     * Member fields are global rather than scoped to a channel, so no
     * settings are required to look them up.
     */
    public function getCustomFields(array $settings = []): array
    {
        $customFields = [];

        $fields = ee('Model')->get('MemberField')
            ->order('m_field_order', 'ASC')
            ->all();

        foreach ($fields as $row) {
            $customFields[$row->m_field_name]['id'] = $row->m_field_id;
            $customFields[$row->m_field_name]['format'] = $row->m_field_fmt;
            $customFields[$row->m_field_name]['type'] = $row->m_field_type;
            $customFields[$row->m_field_name]['name'] = $row->m_field_name;
        }

        return $customFields;
    }

    public function getCustomFieldSettings(string $fieldName): array
    {
        $field = ee('Model')
            ->get('MemberField')
            ->filter('m_field_name', $fieldName)
            ->first();

        if (!$field) {
            return [];
        }

        return $field->m_field_settings ?? [];
    }

    /**
     * Members that weren't part of this import run.
     *
     * Scoped to the roles this import actually assigns, so an import that
     * manages one role can never touch members of an unrelated role. Super
     * Admins and already anonymized/banned members are always excluded, so an
     * import can't lock out an admin or repeatedly re-queue the same accounts.
     */
    public function getRecordsToDelete(array $importedIds, array $settings = []): array
    {
        $roleIds = $this->getManagedRoleIds($settings, $importedIds);

        // Without a known role scope, do nothing rather than sweep the whole site.
        if (empty($roleIds)) {
            return [];
        }

        return ee('Model')->get('Member')
            ->filter('member_id', 'NOT IN', $importedIds)
            ->filter('role_id', 'IN', $roleIds)
            ->filter('role_id', '!=', self::BANNED_ROLE_ID)
            ->filter('role_id', '!=', self::SUPER_ADMIN_ROLE_ID)
            ->all()
            ->pluck('member_id');
    }

    /**
     * The set of roles an import manages: the configured default role, plus the
     * roles actually assigned to the members this run imported. Scoping to roles
     * observed in the run — rather than every role that a mapped role field
     * could theoretically produce — keeps an import from reaching into roles it
     * never touches. Super Admin and Banned are never managed.
     */
    public function getManagedRoleIds(array $settings = [], array $importedIds = []): array
    {
        $roleIds = [];

        $defaultRole = $settings['config']['member_role'] ?? '';

        if ($defaultRole !== '' && $defaultRole !== null) {
            $roleIds[] = (int) $defaultRole;
        }

        // When roles come from the feed, the imported members themselves are the
        // authority on which roles this import actually manages.
        if (!empty($settings['config']['member_role_field']) && !empty($importedIds)) {
            $assigned = ee('Model')->get('Member')
                ->filter('member_id', 'IN', $importedIds)
                ->all()
                ->pluck('role_id');

            $roleIds = array_merge($roleIds, array_map('intval', $assigned));
        }

        $roleIds = array_unique(array_filter($roleIds));

        return array_values(array_diff($roleIds, [
            self::SUPER_ADMIN_ROLE_ID,
            self::BANNED_ROLE_ID,
        ]));
    }

    public function supportsCategories(): bool
    {
        return false;
    }
}
