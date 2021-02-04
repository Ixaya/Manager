<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Private_Controller extends Public_Controller {


	
	function __construct() {
				
		//you can change the theme from here, or from manager.php inside /application/config/
		//$this->_theme = 'default';
		//$this->_theme = 'soon';
		
		parent::__construct();
		
		if(!$this->ion_auth->logged_in())	
		{
			redirect('/');
		}
	}
}
