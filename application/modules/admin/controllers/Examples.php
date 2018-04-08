<?php

class Examples extends Admin_Controller {

    function __construct() {
        parent::__construct();

        $this->load->model(array('admin/example'));
    }

    public function index() {	    
        $examples = $this->example->get_all();
        $data['examples'] = $examples;
        $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "examples_list";
        $this->load->view($this->_container, $data);
    }

    public function create() {
        if ($this->input->post('title')) {
            $data['title'] = $this->input->post('title');
            $data['example'] = $this->input->post('example');
            $this->example->insert($data);
            redirect('/admin/examples', 'refresh');
        }

        $this->load->helper(array('form','ui'));
        $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "examples_create";
        $this->load->view($this->_container, $data);
    }

    public function edit($id) {
        if ($this->input->post('title')) {
            $data['title'] = $this->input->post('title');
            $data['example'] = $this->input->post('example');
            $this->example->update($data, $id);

            redirect('/admin/examples', 'refresh');
        }

        $this->load->helper(array('form','ui'));
        $example = $this->example->get($id);
        $data['example'] = $example;
        $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "examples_edit";
        $this->load->view($this->_container, $data);
    }

    public function delete($id) {
        $this->example->delete($id);

        redirect('/admin/examples', 'refresh');
    }

}
