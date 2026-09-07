<?php
echo form_open($form_action);
echo form_hidden("datagrab_step", "settings");
?>

<div class="box mb">
    <div class="tbl-ctrls">

        <h1>Import Settings</h1>

        <?php

        $this->table->set_template($cp_table_template);
        $this->table->set_heading('Setting', 'Value');

        $import = array(
            array(
                form_label('Channel', 'channel') .
                '<div class="subtext">Select the channel to import the data into</div>',
                form_dropdown('channel', $channels, $channel, ' id="channel"')
            )
        );

        echo $this->table->generate($import);

        $this->table->clear()

        ?>


        <h1>Datatype settings</h1>

        <?php

        $this->table->set_template($cp_table_template);
        $this->table->set_heading('Setting', 'Value');

        unset($datatype["allow_comments"]);
        unset($datatype["allow_subloop"]);
        foreach ($datatype as $key => $value) {
            $this->table->add_row(
                ucfirst($key),
                $value
            );
        }

        foreach ($settings as $row) {
            if (count($row) == 1) {
                $this->table->add_row(
                    array(
                        'colspan' => 2,
                        'data' => $row[0],
                        'class' => 'box'
                    )
                );
            }
            if (count($row) == 2) {
                $this->table->add_row(
                    array("data" => $row[0], "width" => "50%"),
                    $row[1]
                );
            }
        }

        echo $this->table->generate();

        ?>

        <p><input type="submit" value="Check settings" class="btn action"/></p>

        <?php echo form_close(); ?>

    </div>
</div>
