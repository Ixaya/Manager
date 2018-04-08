<?php

class Sysusers extends Admin_Controller {

    function __construct() {
        parent::__construct();

        $group = 'admin';
        $this->load->model('admin/user');

		$this->load->helper('form');
		
        if (!$this->ion_auth->in_group($group))
        {
            $this->session->set_flashdata('message', 'You must be an administrator to view the users page.');
            redirect('admin/dashboard');
        }
        $this->load->helper(array('form', 'url', 'image'));
    }

    public function index() {
	    $offset = "0";

//         $users = $this->ion_auth->users()->result();
        $users = $this->user->get_users($offset);
        $data['users'] = $users;
        $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "sysusers_list";
        $this->load->view($this->_container, $data);
    }
    
	public function index_ajax() {
		$users = $this->user->get_all('id,first_name,email,ip_address,created_on,last_activity_date',null,null,10);
        echo json_encode($users);
    }


	public function index_details($id)
    {
	    $user = array();
	    
	    if (isset($id)) {
        	$addresses = $this->address->get_all("*", array('user_id' => $id));
		}
		$data['addresses'] = $addresses;	  
		
		$users = $this->ion_auth->users()->result();
        $data['users'] = $users;
        $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "sysusers_list";
        $this->load->view($this->_container, $data);
    }
	
    public function create() {
        if ($this->input->post('username')) {
//             $client_id = $this->input->post('client_id');
            $username = $this->input->post('username');
            $password = $this->input->post('password');
            $email = $this->input->post('email');
           // $active = $this->input->post('active');
            $group_id = array( $this->input->post('group_id'));
            
// 			$client_id = array( $this->input->post('client_id'));


            $additional_data = array(
                'first_name' => $this->input->post('first_name'),
                'last_name' => $this->input->post('last_name'),
                'username' 	=> $this->input->post('username'),
                'phone' 	=> $this->input->post('phone')
            );
            
            if($this->input->post('active') == 1)
            	$additional_data['active'] = 1;
            else
            	$additional_data['active'] = 0;
            

            $user = $this->ion_auth->register($email, $password, $email, $additional_data, $group_id);
            

            if(!$user)
            {
                $errors = $this->ion_auth->errors();
                echo $errors;
                die('done');
            }
            else
            {
                redirect('/admin/sysusers', 'refresh');
            }


        }
        $data['groups'] = $this->ion_auth->groups()->result();
        $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "sysusers_create";
        $this->load->view($this->_container, $data);
    }

    public function edit($id) {
        if ($this->input->post('first_name')) {
            $data['ip_address'] = $this->input->post('ip_address');
            $data['username'] = $this->input->post('username');
            $data['first_name'] = $this->input->post('first_name');
            $data['last_name'] = $this->input->post('last_name');
            $data['email'] = $this->input->post('email');
            $data['phone'] = $this->input->post('phone');
            $data['active'] = $this->input->post('active');
            $data['company'] = $this->input->post('company');
            $data['facebook_id'] = $this->input->post('facebook_id');
            $data['activation_code'] = $this->input->post('activation_code');
            $data['created_on'] = $this->input->post('created_on');
            $data['last_login'] = $this->input->post('last_login');
            $data['fb_token'] = $this->input->post('fb_token');
            $data['fb_login'] = $this->input->post('fb_login');
            $data['fb_last_sync'] = $this->input->post('fb_last_sync');
            $data['fb_response'] = $this->input->post('fb_response');
            $data['image_url'] = $this->input->post('image_url');
            if($this->input->post('is_public') == 1)
            	$data['is_public'] = $this->input->post('is_public');
			else
				$data['is_public'] = 0;
            
            if($this->input->post('password')){
		        $data['passwprd'] = $this->ion_auth->hash_password($new, $this->ion_auth->salt);

            }
            
            
			$data['password'] = $this->input->post('password');
            $group_id = $this->input->post('group_id');
            
            $getFb = $this->user->get_fb($id);
			$data['getFb'] = $getFb;

            $this->ion_auth->remove_from_group('', $id);
            $this->ion_auth->add_to_group($group_id, $id);

            $this->ion_auth->update($id, $data);
            
            //redirect('/admin/sysusers', 'refresh');
        }

        $this->load->helper('ui');

        $data['groups'] = $this->ion_auth->groups()->result();
        $data['user'] = $this->ion_auth->user($id)->row();
        $data['user_group'] = $this->ion_auth->get_users_groups($id)->row();
        $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "sysusers_edit";
        $this->load->view($this->_container, $data);
    }

    public function delete($id) {
        $this->ion_auth->delete_user($id);

        redirect('/admin/sysusers', 'refresh');
    }
    
    
    public function do_upload($id)
    {
//             $config['upload_path']          = '/home/ixayanet/app/public/media/';
			$config['upload_path']          = '/home/ixayanet/app/public/media/';
            $config['allowed_types']        = 'gif|jpg|png';
            $config['max_size']             = 2048; //2MB (PHP Max in this config)
//             $config['max_width']            = 1024;
//             $config['max_height']           = 1024;
            $config['max_width']            = 0; // no size restriction
            $config['max_height']           = 0; // no size restriction

			$config['encrypt_name']			= true; 
			$config['remove_spaces']		= true; 
			
            $this->load->library('upload', $config);

            if ( ! $this->upload->do_upload('userfile'))
            {
		            $this->session->set_flashdata('message', $this->upload->display_errors());
		            $this->edit($id);
            }
            else
            {		$upload_data = $this->upload->data();
		            $data['image_url']   = base_url("/media/".$upload_data['file_name']);
		            $data['image_name']  = $upload_data['file_name'];
				    $this->user->update($data, $id);				    
		            redirect('/admin/sysusers', 'refresh');
            }
    }
    public function detail($id)
    {

		$user = $this->user->get($id);
        $data['user'] = $user;
	    
	    $getFb = $this->user->get_fb($id);
        $data['getFb'] = $getFb;
         
        $getUserLocation = $this->user->get_user_location($id);
        $data['getUserLocation'] = $getUserLocation;
        
        $getUserLists = $this->user->get_user_lists($id);
        $data['getUserLists'] = $getUserLists;
        
        $getUserFriends = $this->user->get_user_friends($id);
        $data['getUserFriends'] = $getUserFriends;
        
        $getUserEvent = $this->user->get_user_event($id);
        $data['getUserEvent'] = $getUserEvent;
        
        $getUserNotifications = $this->user->get_user_notifications($id);
        $data['getUserNotifications'] = $getUserNotifications;
        
        $getUserChats = $this->user->get_user_chats($id);
        $data['getUserChats'] = $getUserChats;
        
        $getUserFavoriteProduct = $this->user->get_user_favorite_product($id);
        $data['getUserFavoriteProduct'] = $getUserFavoriteProduct;
        
        $getOsKind = $this->user->get_os_kind($id);
        $data['getOsKind'] = $getOsKind;
        
	    $address = $this->user->get_address($id);
        $data['getAddress'] = $address;
        
        $paymentMethod = $this->user->get_payment_method($id);
        $data['paymentMethod'] = $paymentMethod;
	    
	    $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "sysusers_detail";
        $this->load->view($this->_container, $data);
    }
    public function chats($id)
    {
	    $getAllUserChats = $this->user->get_all_user_chats($id);
        $data['getAllUserChats'] = $getAllUserChats;
        
	    $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "sysusers_chats";
        $this->load->view($this->_container, $data);
	}
	
	public function info()
    {
	    $getIosInstallations = $this->user->get_ios_installations();
        $data['getIosInstallations'] = $getIosInstallations;
        
        $getAndroidInstallations = $this->user->get_android_installations();
        $data['getAndroidInstallations'] = $getAndroidInstallations;
        
        $getCountUserMale = $this->user->get_count_user_male();
        $data['getCountUserMale'] = $getCountUserMale;
        
        $getCountUserFemale = $this->user->get_count_user_female();
        $data['getCountUserFemale'] = $getCountUserFemale;
        
        $getMostSharedProduct = $this->user->get_most_shared_products();
        $data['getMostSharedProduct'] = $getMostSharedProduct;
        
        $getTopMatchedProducts = $this->user->get_top_matched_products();
        $data['getTopMatchedProducts'] = $getTopMatchedProducts;
        
        $getTopLiverpoolProducts = $this->user->get_top_liverpool_products();
        $data['getTopLiverpoolProducts'] = $getTopLiverpoolProducts;        
        
        $getTopBestbuyProducts = $this->user->get_top_bestbuy_products();
        $data['getTopBestbuyProducts'] = $getTopBestbuyProducts;
        
        $getTopClaroProducts = $this->user->get_top_claro_products();
        $data['getTopClaroProducts'] = $getTopClaroProducts;
        
        $getTopInnovaProducts = $this->user->get_top_innova_products();
        $data['getTopInnovaProducts'] = $getTopInnovaProducts;
        
        $getTopPetcoProducts = $this->user->get_top_petco_products();
        $data['getTopPetcoProducts'] = $getTopPetcoProducts;
	    
	    $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "sysusers_info";
        $this->load->view($this->_container, $data);
	}

}
