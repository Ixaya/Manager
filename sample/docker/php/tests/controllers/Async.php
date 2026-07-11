<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Async extends APP_Rest_Controller
{
    public function __construct()
    {
        // No auth_override — this controller uses the app's normal API key
        // auth (Ion Auth login -> X-Api-Key), same as any other endpoint.
        parent::__construct();

        // Defense in depth: this module must never run in production, even
        // if it were ever accidentally baked into a prod image. The build
        // guard (INCLUDE_TEST_MODULE, default false) is the primary control;
        // this is the fallback if that ever fails.
        if (ENVIRONMENT === 'production') {
            log_message('error', 'SECURITY: smoke-test module (tests/Async) was hit in a production environment. This module must never ship in a production build.');
            $this->response(['status' => 0, 'message' => 'Not found'], REST_Controller::HTTP_FORBIDDEN);
        }
    }

    /**
     * GET /tests/async
     * Dispatches manager/tools/plan as a background CLI job. A thin
     * live-wiring probe, not a test framework — no assertions/fixtures.
     */
    public function index_get()
    {
        $this->load->library('async_exec_lib');
        $this->async_exec_lib->cli_run_uri('manager/tools/plan');
        $this->response(['status' => 1, 'message' => 'async job dispatched'], 200);
    }
}
