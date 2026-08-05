<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sepomex extends MY_Model
{
	/**
	 * Every distinct state.
	 * @return array<int,array{id_estado:int,estado:string}>|false
	 */
	public function get_all_states(): array|false
	{
		return $this->query('SELECT DISTINCT id_estado, estado FROM sepomex', null);
	}

	/**
	 * Every distinct municipio in a state, by state id.
	 * @return array<int,array{id_municipio:int,municipio:string}>|false
	 */
	public function get_municipios_by_state_id(int $id_estado): array|false
	{
		$query = 'SELECT DISTINCT id_municipio, municipio FROM sepomex WHERE id_estado=? ORDER BY id_municipio';

		return $this->query($query, [$id_estado]);
	}

	/**
	 * Every distinct municipio in a state, by state name.
	 * @return array<int,array{id_municipio:int,municipio:string}>|false
	 */
	public function get_municipios_by_state(string $estado): array|false
	{
		$query = 'SELECT DISTINCT id_municipio, municipio FROM sepomex WHERE estado=? ORDER BY id_municipio';

		return $this->query($query, [$estado]);
	}

	/**
	 * Every state/municipio/asentamiento combination for a postal code.
	 * @return array<int,array{estado:string,municipio:string,asentamiento:string}>|false
	 */
	public function get_neighborhoods_by_cp(string $cp): array|false
	{
		$query = 'SELECT DISTINCT estado, municipio, asentamiento FROM sepomex WHERE cp=?';

		return $this->query($query, [$cp]);
	}

	/**
	 * Every distinct asentamiento in a municipio.
	 * @return array<int,array{asentamiento:string}>|false
	 */
	public function get_neighborhoods_by_municipio(string $municipio): array|false
	{
		$query = 'SELECT DISTINCT asentamiento FROM sepomex WHERE municipio=? ORDER BY asentamiento ASC';

		return $this->query($query, [$municipio]);
	}

	/** First postal code matching a municipio/asentamiento pair, if any. */
	public function get_cp_by_municipio_and_asentamiento(string $municipio, string $asentamiento): ?string
	{
		$query = 'SELECT DISTINCT cp FROM sepomex WHERE municipio=? AND asentamiento=? ORDER BY cp ASC';
		$result = $this->query($query, [$municipio, $asentamiento]);

		if ($result === false || count($result) === 0) {
			return null;
		}

		return $result[0]['cp'];
	}
}
