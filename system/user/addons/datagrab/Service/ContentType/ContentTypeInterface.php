<?php

namespace BoldMinded\DataGrab\Service\ContentType;

/**
 * Describes how a given import type (entry, file, member) binds to EE's
 * content models. This exists so the shared import pipeline doesn't have to
 * branch on $settings['import']['import_type'] in a dozen places.
 */
interface ContentTypeInterface
{
    /**
     * The value stored in $settings['import']['import_type']
     */
    public function getName(): string;

    /**
     * Queue job class used to import a single row
     */
    public function getImportJobClass(): string;

    /**
     * Queue job class used to delete/expire a single record
     */
    public function getDeleteJobClass(): string;

    /**
     * Prefix used for custom field columns, e.g. field_id_ or m_field_id_
     */
    public function getCustomFieldPrefix(): string;

    /**
     * Name of the field definition model, e.g. ChannelField or MemberField
     */
    public function getFieldModelName(): string;

    /**
     * Custom fields keyed by field name, each with id/format/type/name.
     * Mirrors Importer::fetchCustomFieldsFromChannel()'s return shape.
     */
    public function getCustomFields(array $settings = []): array;

    /**
     * Field settings for a single custom field, or [] when not found.
     */
    public function getCustomFieldSettings(string $fieldName): array;

    /**
     * IDs of existing records that were not part of this import run.
     */
    public function getRecordsToDelete(array $importedIds, array $settings = []): array;

    /**
     * Whether this content type supports categories
     */
    public function supportsCategories(): bool;
}
