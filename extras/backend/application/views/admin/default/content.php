<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (isset($page)) {
	if (isset($module)) {
		$this->load->view("$module/$page");
	} else {
		$this->load->view($page);
	}
}
