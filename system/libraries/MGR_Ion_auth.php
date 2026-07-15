<?php

require dirname(__FILE__) . "/../../third_party/BE/Ion_auth.php";

class Ion_auth extends BE_Ion_auth
{
	/**
	 * client_id
	 *
	 * @return integer|null
	 * @author gumoz
	 **/
	public function get_client_id(): ?int
	{
		if (!$this->allow_session) {
			return null;
		}
		$this->load->library('session');

		$client_id = $this->session->userdata('client_id');
		if (!empty($client_id)) {
			return $client_id;
		}
		return null;
	}
}
