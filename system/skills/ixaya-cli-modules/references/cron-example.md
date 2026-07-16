# Cron job example — incremental sync with checkpoints

Condensed from the sample skeleton's cron module (`Example.php`).
Demonstrates: the CLI guard, `manager_option` checkpoint keys for
incremental syncs, a `force_restart` arg to re-sync from scratch, and
terminal-style progress output.

```php
<?php

class Crons_example extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!$this->input->is_cli_request()) {
            exit('Direct access is not allowed. This is a command line tool, use the terminal');
        }
    }

    public function send_example($force_restart = 0)
    {
        //crontab: */5 * * * * /bin/nice -n 10 /home/example/app/bin/cli_run.sh cron/example/send_example >> /home/example/logs/crons/send_example.log

        $this->load->model(['manager_option', 'example']);

        $limit = 999;
        $start_date = date('Y-m-d H:i:s');

        // checkpoints survive between runs; a batch resumes from the last id,
        // a completed sync resumes from the last sync time
        $time_key = 'example_last_sync';
        $id_key = 'example_last_id';

        if ($force_restart == 1) {
            $this->manager_option->delete($id_key);
            $this->manager_option->delete($time_key);
            $last_update = null;
            $last_id = null;
        } else {
            $last_update = $this->manager_option->get_value($time_key);
            $last_id = $this->manager_option->get_value($id_key);
        }

        $rows = $this->example->get_for_cron($limit, $last_update, $last_id);
        if (empty($rows)) {
            return;
        }

        $total = count($rows);
        $processed = 0;
        foreach ($rows as $row) {
            // process $row ...
            $last_id = $row['id'];
            $processed++;
        }

        // full batch means more rows may remain: checkpoint the id and let the
        // next run continue; a short batch means the sync is complete
        if ($total < $limit) {
            $this->manager_option->save_value($time_key, $start_date);
            $this->manager_option->delete($id_key);
        } else {
            $this->manager_option->save_value($id_key, $last_id);
        }

        echo '++ ' . date('Y-m-d H:i:s') . " Got: $total Processed: $processed Last: $last_id\r\n";
    }
}
```
