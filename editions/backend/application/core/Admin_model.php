<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class Datatable_Model extends MY_Model
{
	public function get_datatable_json($custom = "", $where = "")
	{
		$this->check_connect();

		$where_like = [];
		$where_array = [];
		$search_query = "";
		$limit_query = "";
		$order_query = "";
		$response = [];


		if (!empty($this->table_columns)) {
			if ($where != "") {
				$where_array[] = $where;
			}

			if ($this->where_override) {
				foreach ($this->where_override as $wk => $wo) {
					$where_array[] = $wk . " = " . $wo;
				}
				$response['post'] = json_encode($this->where_override);
			}

			if (isset($_POST['search']) && !empty($_POST['search']['value'])) {
				$word_post = htmlspecialchars($_POST['search']['value']);
				$words = explode(" ", $word_post);

				foreach ($words as $word) {
					$like = [];
					$types = array_column($this->table_columns, 'type');
					$colsKeys = array_keys($types, "STRING");

					if (!empty($colsKeys)) {
						foreach ($colsKeys as $key) {
							$like[] = "lower(" . $this->table_columns[$key]['column'] . ") like lower('%" . $word . "%')";
						}
					}

					$types = array_column($this->table_columns, 'type');
					$colsKeys = array_keys($types, "INT");

					if (!empty($colsKeys)) {
						foreach ($colsKeys as $key) {
							$like[] = "CAST(" . $this->table_columns[$key]['column'] . " as CHAR) LIKE '%" . $word . "%'";
						}
					}

					$where_like[] = implode(" OR ", $like);
				}

				$search_query = "( " . implode(") AND (", $where_like) . " )";
				$where_array[] = $search_query;
			}

			$length = 10;

			if (isset($_POST['length'])) {
				$length = intval($_POST['length']);
			}
			if ($_POST['length'] != '-1' && isset($_POST['start'])) {
				$start = intval($_POST['start']);
				$limit_query .= "LIMIT $start, $length";
			}

			$colNames = array_column($this->table_columns, 'column');

			if (!empty($_POST['order'])) {
				foreach ($_POST['order'] as $col) {
					if (isset($colNames[intval($col['column'])])) {
						$order_query .= " " . $colNames[intval($col['column'])] . " " . $col['dir'] . ",";
					}
				}
				if ($order_query != "") {
					$order_query = " ORDER BY " . rtrim($order_query, ",");
				}
			}

			/*if($where != "" && $search_query != ""){
				$search_query = $where." AND ".$search_query;
			}elseif($where != "" && $search_query == ""){
				$search_query = $where;
			} */


			if (count($where_array) > 0) {
				$search_query = " WHERE " . implode(" AND ", $where_array);
			}

			$result = $this->query("SELECT *,
											   (select count(id) from " . $this->table_name . "
											   " . $search_query . ") as total from " . $this->table_name . "
												" . $search_query . "
												" . $order_query . " " . $limit_query, null);

			if (count($result) > 0) {
				$list_results = [];
				$urlreferences = [];

				if ($custom != "") {
					preg_match_all('#modurl=(.+?)\]#s', $custom, $urlreferences);
				}
				$colNames = array_column($this->table_columns, 'column');
				foreach ($result as $row) {
					$item = [];

					foreach ($colNames as $ky => $col) {
						if (isset($this->table_columns[$ky]['fx'])) {
							$func = $this->table_columns[$ky]['fx'];
							eval('$item[] = ' . $func . ';');
						} else {
							$item[] = $row[$col];
						}
					}

					if ($custom != "") {
						$custom_current = $custom;
						foreach ($urlreferences[0] as $k => $daturl) {

							$columnreferences = [];
							$url_current = $daturl;
							preg_match_all('#modcol=(.+?)\]#s', $daturl, $columnreferences);

							foreach ($columnreferences[1] as $datcol) {
								$url_current = str_replace("[modcol=" . $datcol . "]", $row[$datcol], $url_current);
							}
							$url_current = str_replace("modurl=", "", $url_current);

							$custom_current = str_replace(
								$daturl . "]",
								base_url($url_current),
								$custom_current
							);
						}
						$item[] = str_replace("[", "", $custom_current);
					}
					if (!empty($item)) {
						$list_results[] = $item;
					}
				}
				$response['recordsTotal'] = intval($result[0]['total']);
				$response['recordsFiltered'] = intval($result[0]['total']);
			} else {
				$item = [];
				foreach ($this->table_columns as $column) {
					$item[] = "<td>No data</td>";
				}

				if ($custom != "") {
					$item[] = "";
				}

				$list_results[] = $item;
				$response['recordsTotal'] = 0;
				$response['recordsFiltered'] = 0;
			}



			$response['draw'] = $_POST['draw'];
			$response['data'] = $list_results;



			$json_response = json_encode($response);
			echo ($json_response);

			log_message('DEBUG', $json_response);

			exit;
		} else {
			$response['error'] = "Not declared columns";
		}
	}

	public function get_datatable($config, $where = NULL)
	{
		$this->check_connect();

		if (empty($config)) {
			$dummy_post = '{"draw":"1","columns":[{"data":"0","name":"","searchable":"true","orderable":"true","search":{"value":"","regex":"false"}},{"data":"1","name":"","searchable":"true","orderable":"true","search":{"value":"","regex":"false"}}],"order":[{"column":"0","dir":"asc"}],"start":"0","length":"10","search":{"value":"","regex":"false"}}';
			$config = json_decode($dummy_post, true);
		}

		$where_like = [];
		$where_array = [];
		$search_query = "";
		$limit_query = "";
		$order_query = "";
		$response = [];

		if (empty($this->table_columns))
			return ['error' => 'Columns not declared'];

		if ($where != NULL)
			$where_array[] = $where;

		if ($this->where_override) {
			foreach ($this->where_override as $wk => $wo) {
				$where_array[] = $wk . " = " . $wo;
			}
			$response['post'] = json_encode($this->where_override);
		}

		if (isset($config['search']) && !empty($config['search']['value'])) {
			$word_post = htmlspecialchars($config['search']['value']);
			$words = explode(" ", $word_post);

			foreach ($words as $word) {
				$like = [];

				//Restructure so its only one foreach
				$types = array_column($this->table_columns, 'type');
				$colsKeys = array_keys($types, "STRING");

				if (!empty($colsKeys)) {
					foreach ($colsKeys as $key) {
						$like[] = "lower(`" . $this->table_columns[$key]['column'] . "`) like lower('%" . $word . "%')";
					}
				}

				$types = array_column($this->table_columns, 'type');
				$colsKeys = array_keys($types, "INT");

				if (!empty($colsKeys)) {
					foreach ($colsKeys as $key) {
						$like[] = "CAST(`" . $this->table_columns[$key]['column'] . "` as CHAR) LIKE '%" . $word . "%'";
					}
				}

				$where_like[] = implode(" OR ", $like);
			}

			$search_query = "( " . implode(") AND (", $where_like) . " )";
			$where_array[] = $search_query;
		}

		$length = 10;
		if (isset($config['length']))
			$length = intval($config['length']);

		if ($config['length'] != '-1' && isset($config['start'])) {
			$start = intval($config['start']);
			$limit_query .= "LIMIT $start, $length";
		}

		$colNames = array_column($this->table_columns, 'column');

		if (!empty($config['order'])) {
			foreach ($config['order'] as $col) {
				if (isset($colNames[intval($col['column'])])) {
					$order_query .= " `" . $colNames[intval($col['column'])] . "` " . $col['dir'] . ",";
				}
			}
			if ($order_query != "") {
				$order_query = " ORDER BY " . rtrim($order_query, ",");
			}
		}

		if (count($where_array) > 0)
			$search_query = " WHERE " . implode(" AND ", $where_array);

		$result = $this->query("SELECT *
												FROM " . $this->table_name . "
												" . $search_query . "
												" . $order_query . " " . $limit_query, null);

		if (count($result) > 0) {
			$list_results = [];

			$colNames = array_column($this->table_columns, 'column');
			foreach ($result as $row) {
				$item = [];

				foreach ($colNames as $ky => $col) {
					if (isset($this->table_columns[$ky]['fx'])) {
						$func = $this->table_columns[$ky]['fx'];
						eval('$item[] = ' . $func . ';');
					} else {
						$item[] = $row[$col];
					}
				}

				if (!empty($item)) {
					$list_results[] = $item;
				}
			}

			$count_result = $this->query("SELECT count(id) AS total FROM " . $this->table_name . " " . $search_query);
			$response['recordsTotal'] = intval($count_result[0]['total']);
			$response['recordsFiltered'] = intval($count_result[0]['total']);
		} else {
			$item = [];
			foreach ($this->table_columns as $column) {
				$item[] = "<td>No data</td>";
			}

			$list_results[] = $item;
			$response['recordsTotal'] = 0;
			$response['recordsFiltered'] = 0;
		}

		$response['draw'] = $config['draw'];
		$response['data'] = $list_results;

		return $response;
	}
}
