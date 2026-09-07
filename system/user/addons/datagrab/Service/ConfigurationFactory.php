<?php

namespace BoldMinded\DataGrab\Service;

class ConfigurationFactory
{
    public function __construct(
        private bool $allowComments = false,
        private array $authors = [],
        private array $authorFields = [],
        private array $categoryGroups = [],
        private array $customFields = [],
        private array $dataFields = [],
        private array $defaultSettings = [],
        private int $importId = 0,
        private array $statusFields = [],
        private array $uniqueFields = [],
        private array $roles = [],
        private bool $isMemberImport = false,
    ) {
    }

    private function makeNote(string $text): string
    {
        return ee('View')
            ->make('ee:_shared/form/fields/note')
            ->render([
                'value' => $text
            ]);
    }

    public function fieldSetImportProperties(): array
    {
        // Set data
        if ($this->importId === 0) {
            $name = '';
            $description = '';
            $passkey = '';
            $migration = 0;
        } else {
            ee()->db->where('id', $this->importId);
            $query = ee()->db->get('exp_datagrab');
            $row = $query->row_array();

            $name = $row['name'] ?? '';
            $description = $row['description'] ?? '';
            $passkey = $row['passkey'] ?? '';
            $migration = $row['migration'] ?: 0;
        }

        $passKeyField = form_input(
                [
                    'name' => 'dg_import_props[passkey]',
                    'id' => 'passkey',
                    'value' => $passkey,
                ]
            ) . '<br />' .
            form_button(
                [
                    'id' => 'generate',
                    'name' => 'generate',
                    'content' => 'Generate random key',
                    'class' => 'button button--secondary button--small'
                ]
            );

        $fieldOptions[] = [
            'title' => 'Name',
            'desc' => 'A title for the import',
            'fields' => [
                'dg_import_props[name]' => [
                    'required' => true,
                    'type' => 'text',
                    'value' => $name,
                ],
                'id' => [
                    'required' => true,
                    'type' => 'hidden',
                    'value' => $this->importId ?? 0,
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Description',
            'desc' => 'A description of the import',
            'fields' => [
                'dg_import_props[description]' => [
                    'type' => 'textarea',
                    'value' => $description,
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Passkey',
            'desc' => 'Add a passkey to increase security when running imports. Starting with DataGrab 6.1 all newly created imports will require a passkey.',
            'fields' => [
                'dg_import_props[passkey]' => [
                    'type' => 'html',
                    'content' => $passKeyField,
                ]
            ]
        ];
//                [
//                    'title' => 'Migration',
//                    'desc' => 'Create a migration of this import configuration when saving?',
//                    'fields' => [
//                        'dg_import_props[migration]' => [
//                            'type' => 'toggle',
//                            'value' => $migration,
//                        ]
//                    ]
//                ],

        return $fieldOptions;
    }

    public function fieldSetCustom(): array
    {
        $output = '';

        foreach ($this->customFields as $field) {
            $output .= ee('View')
                ->make('datagrab:panel')
                ->render([
                    'heading' => $field['label'],
                    'html' => $field['value']
                ]);
        }

        $fieldOptions[] = [
            'fields' => [
                'note' => [
                    'type' => 'html',
                    'content' => $this->makeNote('Assign values to use for the channel\'s custom fields. 
                    You can leave values blank, unless they are set to required.') .
                        '<br /><a href="#" class="js-datagrab-toggle-all-custom-fields" data-text-collapsed="Expand All" data-text-expanded="Collapse All" data-state="collapsed">Expand All</a>',
                ]
            ]
        ];

        $fieldOptions[] = [
            'fields' => [
                'custom_fields' => [
                    'type' => 'html',
                    'content' => $output,
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function fieldSetDefault(): array
    {
        $fieldOptions[] = [
            'fields' => [
                'note' => [
                    'type' => 'html',
                    'content' => $this->makeNote('Choose which values to use for the standard channel fields'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Title',
            'desc' => 'The entry\'s title.',
            'fields' => [
                'title' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'required' => true,
                    'value' => $this->defaultSettings['title'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Title Suffix',
            'desc' => 'If defined, the value of this field will be added to the end of the Title field.
                        This can be used to combine values to create unique Titles.',
            'fields' => [
                'title_suffix' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['title_suffix'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'URL Title',
            'desc' => 'The entry\'s URL title. If this is not set then the URL title will be derived from the entry\'s title.',
            'fields' => [
                'url_title' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['url_title'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'URL Suffix',
            'desc' => 'If defined, the value of this field will be added to the end of the URL Title field.
                        This can be used to combine values to create unique URL Titles.',
            'fields' => [
                'url_title_suffix' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['url_title_suffix'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Date',
            'desc' => 'Leave blank to set the entry\'s date to the time of import.',
            'fields' => [
                'date' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['date'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Expiration Date',
            'desc' => 'Leave blank if you do not want to set an expiration date.',
            'fields' => [
                'expiry_date' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['expiry_date'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function fieldSetFileDefault(): array
    {
        $fieldOptions[] = [
            'fields' => [
                'note' => [
                    'type' => 'html',
                    'content' => $this->makeNote('Choose which values to use for the standard channel fields'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Title',
            'desc' => 'The file\'s title.',
            'fields' => [
                'title' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'required' => true,
                    'value' => $this->defaultSettings['title'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Title Suffix',
            'desc' => 'If defined, the value of this field will be added to the end of the Title field.
                        This can be used to combine values to create unique Titles.',
            'fields' => [
                'title_suffix' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['title_suffix'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Date',
            'desc' => 'Leave blank to set the file\'s upload date to the time of import.',
            'fields' => [
                'date' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['date'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function fieldSetMemberDefault(): array
    {
        $fieldOptions[] = [
            'fields' => [
                'note' => [
                    'type' => 'html',
                    'content' => $this->makeNote('Choose which values to use for the standard member fields.
                        Imported members are assigned a randomly generated password and will need to use
                        the "forgot password" flow to set their own.'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Username',
            'desc' => 'The member\'s username. Required unless an email address is mapped.',
            'fields' => [
                'username' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['username'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Email Address',
            'desc' => 'The member\'s email address. Required by ExpressionEngine and must be unique.',
            'fields' => [
                'email' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'required' => true,
                    'value' => $this->defaultSettings['email'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Screen Name',
            'desc' => 'Leave blank to use the username as the screen name.',
            'fields' => [
                'screen_name' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['screen_name'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Join Date',
            'desc' => 'Leave blank to set the member\'s join date to the time of import.',
            'fields' => [
                'date' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['date'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Default Role',
            'desc' => 'The role assigned to imported members.',
            'fields' => [
                'member_role' => [
                    'type' => 'dropdown',
                    'choices' => $this->roles,
                    'value' => $this->defaultSettings['member_role'] ?? 5,
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Role Field',
            'desc' => 'Optionally assign the role from a field in the import data. Accepts either a
                role name or a role ID. Falls back to the default role above when no match is found.',
            'fields' => [
                'member_role_field' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['member_role_field'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function fieldSetMemberHandleDuplicates(): array
    {
        $fieldOptions[] = [
            'fields' => [
                'note' => [
                    'type' => 'html',
                    'content' => $this->makeNote('Determine what happens if the import is run again'),
                ]
            ],
        ];

        $fieldOptions[] = [
            'title' => 'Use these fields to check for duplicates',
            'desc' => 'If a member matching these field\'s values already exists, do not create a new member.
                    Defaults to matching on whichever of username or email is mapped.',
            'fields' => [
                'unique' => [
                    'type' => 'checkbox',
                    'choices' => [
                        'username' => 'Username',
                        'email' => 'Email Address',
                        'screen_name' => 'Screen Name',
                    ],
                    'value' => $this->defaultSettings['unique'] ?? ['email'],
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Update existing members',
            'desc' => 'If the unique field matches, then update the existing member, otherwise ignore it.
                Passwords and join dates of existing members are never overwritten.',
            'fields' => [
                'update' => [
                    'type' => 'toggle',
                    'value' => $this->defaultSettings['update'] ?? 'y',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Ban members missing from this import',
            'desc' => 'Members that are not present in this import are moved into the <b>Banned</b> role.
                Members are never deleted, so their content and history is preserved. Super Admins are
                always skipped, and only members belonging to the roles this import assigns are affected.',
            'fields' => [
                'delete_old' => [
                    'type' => 'toggle',
                    'value' => $this->defaultSettings['delete_old'] ?? 'n',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Anonymize instead of banning',
            'desc' => 'Use ExpressionEngine\'s anonymization (GDPR right to erasure) on members missing from
                this import, instead of only banning them. In addition to banning, this <b>permanently</b>
                replaces the username, screen name and email address, clears the IP address, avatar and
                photo, and empties every custom member field not marked "exclude from anonymization".
                <b>This cannot be undone.</b>',
            'fields' => [
                'anonymize_deleted' => [
                    'type' => 'toggle',
                    'value' => $this->defaultSettings['anonymize_deleted'] ?? 'n',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function fieldSetCategories(): array
    {
        $fieldOptions = [];
        $categoryGroupIds = array_keys($this->categoryGroups);

        foreach ($this->categoryGroups as $groupId => $label) {
            $fieldOptions[] = [
                'fields' => [
                    'note' => [
                        'type' => 'html',
                        'content' => $this->makeNote(sprintf('Add categories to the category group: <b>%s</b>', $label)),
                    ],
                    'c_groups' => [
                        'type' => 'hidden',
                        'value' => implode('|', $categoryGroupIds),
                    ]
                ],
            ];

            $fieldOptions[] = [
                'title' => 'Default Category',
                'desc' => 'Assign this category to every entry.',
                'fields' => [
                    'cat_default_' . $groupId => [
                        'type' => 'dropdown',
                        'choices' => $this->dataFields,
                        'value' => $this->defaultSettings['cat_default_' . $groupId] ?? '',
                    ]
                ]
            ];

            $fieldOptions[] = [
                'title' => 'Categories',
                'desc' => 'Assign categories from this value to the entry.',
                'fields' => [
                    'cat_field_' . $groupId => [
                        'type' => 'dropdown',
                        'choices' => $this->dataFields,
                        'value' => $this->defaultSettings['cat_field_' . $groupId] ?? '',
                    ]
                ]
            ];

            $fieldOptions[] = [
                'title' => 'Category Delimiter',
                'desc' => 'eg, "One, Two, Three" will create 3 categories if the delimiter is a comma.',
                'fields' => [
                    'cat_delimiter_' . $groupId => [
                        'type' => 'text',
                        'value' => $this->defaultSettings['cat_delimiter_' . $groupId] ?? ',',
                    ]
                ]
            ];

            $fieldOptions[] = [
                'title' => 'Sub-Category Delimiter',
                'desc' => 'eg, "Parent/Child/Grand Child" will create a nested heirarchy of categories.',
                'fields' => [
                    'cat_sub_delimiter_' . $groupId => [
                        'type' => 'text',
                        'value' => $this->defaultSettings['cat_sub_delimiter_' . $groupId] ?? '/',
                    ]
                ]
            ];

            $fieldOptions[] = [
                'title' => 'Allow Numeric Category Names',
                'desc' => 'By default integers are assumed to be existing category IDs you want to assign to the entry. 
                            Enabling this setting will allow you to create new or assign existing categories with numeric 
                            values as the category name to an entry.',
                'fields' => [
                    'cat_allow_numeric_names_' . $groupId => [
                        'type' => 'dropdown',
                        'choices' => [0 => 'No', 1 => 'Yes'],
                        'value' => $this->defaultSettings['cat_allow_numeric_names_' . $groupId] ?? '',
                    ],
                ]
            ];
        }

        return $fieldOptions;
    }

    public function fieldSetHandleDuplicates(): array
    {
        $fieldOptions[] = [
            'fields' => [
                'note' => [
                    'type' => 'html',
                    'content' => $this->makeNote('Determine what happens if the import is run again'),
                ]
            ],
        ];

        $fieldOptions[] = [
            'title' => 'Use these fields to check for duplicates',
            'desc' => 'If an entry matching these field\'s values already exists, do not create a new entry.
                    Complex fields such as Grid, Relationship, Fluid, Bloqs etc are not available to use for duplicate checks.',
            'fields' => [
                'unique' => [
                    'type' => 'checkbox',
                    'choices' => array_filter($this->uniqueFields),
                    'value' => $this->defaultSettings['unique'] ?? 'title',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Update existing entries',
            'desc' => 'If the unique field matches, then update the original entry, otherwise ignore it.',
            'fields' => [
                'update' => [
                    'type' => 'toggle',
                    'value' => $this->defaultSettings['update'] ?? 'y',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Delete old entries',
            'desc' => 'Delete entries from this channel that are not updated by this import. Please <a href="https://docs.boldminded.com/datagrab/docs/configuration-options#deleteoldentries">read the documentation</a> if using this option.',
            'fields' => [
                'delete_old' => [
                    'type' => 'toggle',
                    'value' => $this->defaultSettings['delete_old'] ?? 'n',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Soft delete old entries',
            'desc' => 'If deleting old entries from this channel, should the status be set to \'Closed\' instead of deleted from the database?',
            'fields' => [
                'soft_delete' => [
                    'type' => 'toggle',
                    'value' => $this->defaultSettings['soft_delete'] ?? 'n',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Update Edit Date',
            'desc' => 'Set the entry\'s Edit Date field to the time of import?',
            'fields' => [
                'update_edit_date' => [
                    'type' => 'toggle',
                    'value' => $this->defaultSettings['update_edit_date'] ?? 'n',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Add a timestamp to this field',
            'desc' => 'Add the time of the import to this custom field.',
            'fields' => [
                'timestamp' => [
                    'type' => 'dropdown',
                    'choices' => array_filter($this->uniqueFields, function ($fieldName) {
                        return !in_array($fieldName, ['title', 'url_title']);
                    }, ARRAY_FILTER_USE_KEY),
                    'value' => $this->defaultSettings['timestamp'] ?? 'n',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function fieldSetComments()
    {
        if (!$this->allowComments) {
            $fieldOptions[] = [
                'fields' => [
                    'note' => [
                        'type' => 'html',
                        'content' => $this->makeNote('Comments are disabled in this channel.'),
                    ]
                ],
            ];

            return $fieldOptions;
        }

        $fieldOptions[] = [
            'fields' => [
                'note' => [
                    'type' => 'html',
                    'content' => $this->makeNote('Comments are only added when an entry in imported for the first time. Running a subsequent import will update the entry, but not the comments. Please delete the entry to force new comments to be added.'),
                ]
            ],
        ];

        $fieldOptions[] = [
            'title' => 'Import comments?',
            'desc' => 'Add comments for this entry.',
            'fields' => [
                'import_comments' => [
                    'type' => 'toggle',
                    'value' => $this->defaultSettings['import_comments'] ?? 'n',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Comment Author',
            'fields' => [
                'comment_author' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['comment_author'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Comment Author Email',
            'fields' => [
                'comment_email' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['comment_email'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Comment Author URL',
            'fields' => [
                'comment_url' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['comment_url'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Comment Date',
            'fields' => [
                'comment_date' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['comment_date'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Comment Body',
            'fields' => [
                'comment_body' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['comment_body'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function fieldSetAdditionalOptions(): array
    {
        $fieldOptions = [];

        // Authors, statuses and the publish date offset are all entry concepts.
        // Member imports have no author to assign and no status to set.
        if ($this->isMemberImport) {
            $fieldOptions[] = [
                'title' => 'Import in batches',
                'desc' => 'Set the number of members the consumer will import at a time. If you have issues with a server timing out, try reducing the number.',
                'fields' => [
                    'limit' => [
                        'type' => 'text',
                        'value' => $this->defaultSettings['limit'] ?? '50',
                    ]
                ]
            ];

            return $fieldOptions;
        }

        $fieldOptions[] = [
            'title' => 'Default Author',
            'desc' => 'By default, assign entries to this author',
            'fields' => [
                'author' => [
                    'type' => 'dropdown',
                    'choices' => $this->authors,
                    'value' => $this->defaultSettings['author'] ?? array_key_first($this->authors ?? []),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Author',
            'desc' => 'Assign the entry to the member in this field. <b>Members will not be created.</b> If the member does not exist the default author will be used.',
            'fields' => [
                'author_field' => [
                    'type' => 'dropdown',
                    'choices' => $this->dataFields,
                    'value' => $this->defaultSettings['author_field'] ?? '1',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Author Field Value',
            'desc' => 'Select the type of member data that the author field contains.',
            'fields' => [
                'author_check' => [
                    'type' => 'dropdown',
                    'choices' => $this->authorFields,
                    'value' => $this->defaultSettings['author_check'] ?? 'screen_name',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Status',
            'desc' => 'Choose the entry\'s status.',
            'fields' => [
                'entry_status' => [
                    'type' => 'dropdown',
                    'choices' => $this->statusFields,
                    'value' => $this->defaultSettings['entry_status'] ?? $this->defaultSettings['status'] ?? 'default',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Update Status?',
            'desc' => 'When should the entry status be set or updated? If set to "Create or Update" (the default value), 
    the entry\'s status will always be updated to the defined status, or the channel default each time it is imported.
    If set to "Create" it will only be set the first time an entry is imported. Subsequent imports will not change the
    entry\'s current status, even if it differs from the setting value above.',
            'fields' => [
                'update_status' => [
                    'type' => 'dropdown',
                    'choices' => [
                        '' => 'Create or Update',
                        'create' => 'Create Only',
                        'update' => 'Update Only'
                    ],
                    'value' => $this->defaultSettings['update_status'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Publish date offset (in seconds)',
            'desc' => 'Apply an offset to the publish date',
            'fields' => [
                'offset' => [
                    'type' => 'text',
                    'value' => $this->defaultSettings['offset'] ?? '0',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Import in batches',
            'desc' => 'Set the number of entries the consumer will import at a time. If you have issues with a server timing out, try reducing the number.',
            'fields' => [
                'limit' => [
                    'type' => 'text',
                    'value' => $this->defaultSettings['limit'] ?? '50',
                ]
            ]
        ];

        return $fieldOptions;
    }
}
