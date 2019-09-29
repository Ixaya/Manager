<?php

class SepomexService extends CI_Controller {

	function __construct() {
		parent::__construct();

		$this->load->model('admin/sepomex');
	}

	function states()
	{
		$states = $this->sepomex->get_all_states();
		foreach ($states as $key => $list) {
			echo '<option value="' . $list['estado'] . '">'.$list['estado'].'</option>';
		}
	}
		
	function cities_by_state_id()
	{
		$state_id  = $this->input->post('state_id');
		$cities = $this->sepomex->get_cities_by_state($state_id);
		foreach ($cities as $key => $list) {
			echo '<option value="' . $list['municipio'] . '">'.$list['municipio'].'</option>';
		}
	}
	function cities_by_state()
	{
		$state  = $this->input->post('state');
		error_log("cities_by_state".$state);
		$cities = $this->sepomex->get_cities_by_state($state);
		foreach ($cities as $key => $list) {
			echo '<option value="' . $list['municipio'] . '">'.$list['municipio'].'</option>';
		}
	}
	
	public function colonies_by_cp()
	{
		$zip_code  = $this->input->post('zip_code');
		$cities = $this->sepomex->get_colonies_by_cp($zip_code);
		foreach ($cities as $key => $list) {
			echo '<option value="' . $list['asentamiento'] . '">'.$list['asentamiento'].'</option>';
		}
	}
	public function colonies_by_city()
	{
		$city  = $this->input->post('city');
		$colonies = $this->sepomex->get_colonies_by_city($city);
		foreach ($colonies as $key => $list) {
			echo '<option value="' . $list['asentamiento'] . '">'.$list['asentamiento'].'</option>';
		}
	}
	public function cp_by_city_and_colony()
	{
		$city	= $this->input->post('city');
		$colony  = NULL;
		if ($this->input->post('colony'))
			$colony = $this->input->post('colony');
		elseif ($this->input->post('address2'))
			$colony = $this->input->post('address2');

		$cp = $this->sepomex->get_cp_by_city_and_colony($city, $colony);
		echo $cp;
	}

	public function all_by_cp_json($zip = NULL)
	{
		log_message('debug',"all_by_cp_json($zip)");
		error_log("all_by_cp_json($zip)");
		if($zip == NULL)
			$zip  = $this->input->post('zip');

		$rows = $this->sepomex->get_colonies_by_cp($zip);

		$city = "";
		$state = "";
		$colony = "";
		$colonies = array();
		if(count($rows) > 0)
		{
			$city = $rows['0']['municipio'];
			$state = $rows['0']['estado'];
			$colony = $rows['0']['asentamiento'];

			$i = 0;
			foreach($rows as $row)
			{
				$colonies[$i] = $rows[$i]['asentamiento'];
				$i++;
			}
		}
		$data = array('city' => $city,
			'state' => $state,
			'colony' => $colony,
			'colonies' => $colonies,
			'zip' => $zip);

		header('Content-Type: application/json');

		echo (json_encode($data));
		die();
	}
}
