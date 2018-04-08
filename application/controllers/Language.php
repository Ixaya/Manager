<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Language extends CI_Controller
{
    public function __construct() {
        parent::__construct();     
        $this->load->library('session');
        $this->load->helper('url');
    }
    function change($language = "") {
        
        $language = ($language != "") ? $language : "english";
        $this->session->set_userdata('language', $language);
        
        redirect($_SERVER['HTTP_REFERER']);
        
    }
}