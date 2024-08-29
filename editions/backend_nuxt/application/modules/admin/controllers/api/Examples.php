<?php

class Examples extends IX_Rest_Controller {

	function __construct() {
		$this->group_methods['*']['level'] = LEVEL_ADMIN;
		// $this->group_methods['*']['group'] = GROUP_ADMIN;

		parent::__construct();

		$this->load->model('admin/example');
	}

	public function index_get() {
		$data['examples'] = $this->example->get_all();

		$this->response(['status' => 1, 'result' => true, 'response' => $data], REST_Controller::HTTP_OK);
	}

	public function update_post($id){
		$example = $this->example->get($id);
		if (empty($example)) {
			$this->response(['status' => 1, 'result' => false, 'message' => 'Example not found.'], REST_Controller::HTTP_BAD_REQUEST);
		}

		$this->upsert($id);
	}
	public function create() {
		$this->upsert();
	}

	private function upsert($id = NULL)
	{
			$data['title'] = $this->post('title');
			$data['example'] = $this->post('example');

			if ($id){
				$this->example->update($data, $id);
			} else{
				$data['create_date'] = date('Y-m-d H:i:s');
				$id = $this->example->insert($data);
			}

		$this->response(['status' => 1, 'result' => true, 'response' => ['example' => $id]], REST_Controller::HTTP_OK);
	}

	public function delete_get($id) {
		$this->example->delete($id);

		$this->response(['status' => 1, 'result' => true, 'response' => ['example' => $id]], REST_Controller::HTTP_OK);
	}

}
