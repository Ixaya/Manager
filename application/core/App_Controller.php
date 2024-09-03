<?php if (!defined('BASEPATH'))  exit('No direct script access allowed');

class App_Controller extends MY_Controller
{
    public function load_app($page)
    {
        $vue_root = FCPATH . '../vue';
        include "{$vue_root}/views/{$this->_container}/{$page}.html";
    }
}
