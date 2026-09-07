<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_1_07_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->load->dbforge();
        ee()->db->data_cache = [];

        ee()->db->select("id, settings");
        $query = ee()->db->get("ajw_datagrab");
        $results = $query->result_array();

        $datatype_settings = array(
            'filename',
            'delimiter',
            'encloser',
            'skip',
            'path',
            'filter',
            'query',
            'just_posts',
            'username',
            'password',
            'database',
            'server'
        );

        $config_settings = array(
            "type",
            "channel",
            "update",
            "unique",
            "author",
            "author_field",
            "author_check",
            "offset",
            "limit",
            "title",
            "url_title",
            "date",
            "expiry_date",
            "timestamp",
            "delete_old",
            "soft_delete",
            "category_value",
            "cat_field",
            "cat_group",
            "cat_delimiter",
            "id",
            "status",
            "import_comments",
            "comment_author",
            "comment_email",
            "comment_date",
            "comment_url",
            "comment_body",
            "ajw_entry_id" // @todo whats up with this prefix?
        );

        foreach ($results as $row) {
            $old = unserialize($row["settings"]);
            $new = array();

            $new["cf"] = $old;

            $new["import"]["type"] = $old["type"];
            $new["import"]["channel"] = $old["channel"];

            foreach ($datatype_settings as $s) {
                if (isset($old[$s])) {
                    $new["datatype"][$s] = $old[$s];
                }
            }

            foreach ($config_settings as $s) {
                if (isset($old[$s])) {
                    $new["config"][$s] = $old[$s];
                }
            }

            $data = array(
                "settings" => serialize($new)
            );
            ee()->db->where('id', $row["id"]);
            ee()->db->update('ajw_datagrab', $data);
        }
    }
}
