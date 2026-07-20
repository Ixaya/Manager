<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Whoami extends APP_Rest_Controller
{
	public function __construct()
	{
		// No auth_override — this controller uses the app's normal API key
		// auth (Ion Auth login -> X-Api-Key), same as any other endpoint.
		parent::__construct();

		// Defense in depth: this module must never run in production, even
		// if it were ever accidentally baked into a prod image. The build
		// guard (INCLUDE_SMOKE_MODULE, default false) is the primary control;
		// this is the fallback if that ever fails.
		if (ENVIRONMENT === 'production') {
			log_message('error', 'SECURITY: smoke-test module (smoke/Whoami) was hit in a production environment. This module must never ship in a production build.');
			$this->response(['status' => 0, 'message' => 'Not found'], REST_Controller::HTTP_FORBIDDEN);
		}
	}

	/**
	 * GET /smoke/whoami
	 * Returns the authenticated API user's id, email, and Ion Auth group
	 * levels — proves the full login -> X-API-KEY chain end to end.
	 */
	public function index_get()
	{
		if (! isset($this->_apiuser)) {
			$this->response([
				'status'  => 0,
				'message' => 'No valid X-API-KEY supplied — this probe deliberately runs through real auth.',
			], REST_Controller::HTTP_UNAUTHORIZED);
		}

		$this->load->model('ion_auth_model');

		$user = $this->ion_auth_model->user($this->user_id)->row();
		if (empty($user)) {
			$this->response([
				'status'  => 0,
				'message' => 'The authenticated user ID was not found.',
			], REST_Controller::HTTP_NOT_FOUND);
		}

		$groups = [];
		foreach ($this->ion_auth_model->get_users_groups($this->user_id)->result() as $group) {
			$groups[] = [
				'id'    => (int) $group->id,
				'name'  => $group->name,
				'level' => (int) $group->level,
			];
		}

		$this->response([
			'status'   => 1,
			'message'  => 'Authenticated user resolved successfully.',
			'response' => [
				'id'              => (int) $this->user_id,
				'email'           => $user->email,
				'logged_in_level' => (int) $this->logged_in_level,
				'groups'          => $groups,
			],
		], REST_Controller::HTTP_OK);
	}
}
