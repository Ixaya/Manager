<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

class Sepomex extends MY_Model {

	public function __construct() {
		//overrides
		//$this->connection_name = 'catalog';
		//$this->campus_id = 1;
		
		//initialize after overriding
		parent::__construct();
	}
	
	//BASED ON
	//http://www.sepomex.gob.mx/ServiciosLinea/Paginas/DescargaCP.aspx
	
	public function get_all_states()
	{
		$query = "select distinct idEstado, estado from sepomex";
		return $this->query_as_array($query, NULL);
	}
	public function get_cities_by_state_id($state_id)
	{
		$query = "select distinct idMunicipio, municipio from sepomex where idEstado=? order by idMunicipio";
		return $this->query_as_array($query, array($state_id));
	}
	public function get_cities_by_state($state)
	{
		$query = "select distinct idMunicipio, municipio from sepomex where estado=? order by idMunicipio";
		error_log("Query: ".$query);
		return $this->query_as_array($query, array($state));
	}
	public function get_colonies_by_cp($cp)
	{
		$query = "select distinct estado, municipio, asentamiento from sepomex where cp=?";
		return $this->query_as_array($query, array($cp));
	}
	public function get_colonies_by_city($city)
	{
		$query = "select distinct asentamiento from sepomex where municipio=? order by asentamiento asc";
		return $this->query_as_array($query, array($city));
	}
	public function get_cp_by_city_and_colony($city, $colony)
	{
		error_log("get_cp_by_city_and_colony(.".$city.", ".$colony.")");
		error_log($city);
		error_log($colony);

	

		$query = "select distinct cp from sepomex where municipio=? and asentamiento=? order by cp asc";
		$result = $this->query_as_array($query, array($city,$colony));
		if(sizeof($result) > 0)
		{
			return $result[0]['cp'];
					}
		return NULL;
	}




}