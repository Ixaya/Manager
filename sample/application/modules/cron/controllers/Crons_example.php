<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Crons_example extends APP_Cli_Controller
{
	public function send_example($force_restart = 0)
	{
		//crontab: */5  *  *  *  * /bin/nice -n 10 /home/example/app/bin/cli_run.sh cron/crons_example/send_example >> /home/example/logs/crons/send_example.log

		// 'example' stands in for the project model that feeds the sync; none ships, so
		// this stops at "Unable to locate the model" until one is written
		$this->load->model(['manager_option', 'example']);

		$limit = 999;

		$start_date = date('Y-m-d H:i:s');

		$time_key = "example_last_sync";
		$id_key = "example_last_id";

		if ($force_restart != 1) {
			$last_update = $this->manager_option->get_value($time_key);
			$last_id = $this->manager_option->get_value($id_key);
		} else {
			$this->manager_option->delete($id_key);
			$this->manager_option->delete($time_key);
			$last_update = null;
			$last_id = null;
		}

		$rows = $this->example->get_for_cron($limit, $last_update, $last_id);

		if (empty($rows)) {
			return;
		}

		// echo ('-- Start ' . date('Y-m-d H:i:s') . "\r\n");
		$total = count($rows);
		$processed = 0;
		foreach ($rows as $row) {
			//Do something


			$last_update = $row['last_update'];
			$last_id = $row['id'];

			$processed++;
		}

		$result = $this->library->do_something();

		if ($result['status'] == 1) {
			if ($total < $limit) {
				$this->manager_option->save_value($time_key, $start_date);
				$this->manager_option->delete($id_key);
			} else {
				$this->manager_option->save_value($id_key, $last_id);
			}
		} elseif (!empty($result['error'])) {

			echo "## Errors: " . json_encode($result['error']);
		}

		echo('++ ' . date('Y-m-d H:i:s') . " Got(clients): $total Processed: $processed Last: $last_id\r\n");
	}
}
