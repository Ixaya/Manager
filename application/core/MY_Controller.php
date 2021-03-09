<?php if (!defined('BASEPATH'))  exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
	var $_theme;
	var $_container;
	var $_layout;

	var $_use_domain = false;
	var $_domain_id = 0;
	var $domain_client_id;

	var $_theme_kind = 'frontend';

	var $language_file = null;
	var $language_enabled = false;

	var $_css_files = [];
	var $_js_files = [];

	function __construct()
	{
		parent::__construct();
		$this->load->helper('url');

		if($this->_use_domain && !is_cli() && empty($this->_theme))
		{
			$this->load->model('ix_domain');

			$domain_name = $_SERVER['HTTP_HOST'];
			$domain = $this->ix_domain->get_where("domain_name = '$domain_name'");
			if($domain) {
				if (!empty($domain->redirect_url))
					redirect($domain->redirect_url);

				$this->_domain_id = $domain->id;
				$this->domain_client_id = $domain->client_id;

				if (!empty($domain->theme_id)){
					$this->load->model('ix_theme');

					$theme_row = $this->ix_theme->get($domain->theme_id);
					$this->_theme = $theme_row->shortname;
				}
			}
		}

		//construct defaults in case no overrides are setup
		if(empty($this->_theme)){

			// load from config file
			$this->_theme = $this->config->item("{$this->_theme_kind}_theme");


			//$this->_theme = 'default';
		}

		if(empty($this->_container)){
			$this->_container = 'frontend';
		}
		if(empty($this->_layout)){
			$this->_layout = "{$this->_container}/{$this->_theme}/layout";
		} else {
			$this->_layout = "{$this->_container}/{$this->_theme}/{$this->_layout}";
		}

		$this->load->library('session');
		if (!empty($this->session->flashdata('message')) && empty($this->session->flashdata('message-kind'))){
			$this->session->set_flashdata('message-kind', 'info');
		}

		if($this->language_enabled)
		{
			if (!$this->language_file) {
				$this->load->helper('inflector');
			 	$this->language_file = strtolower(get_class($this));
			}

			if(isset($_SESSION['language']))
			{
				$this->config->set_item('language', $_SESSION['language']);
			}
			if(is_array($this->language_file))
			{
				foreach($this->language_file as $file)
				{
					$this->lang->load($file);
				}
			} else {
				$this->lang->load($this->language_file);
			}

			$this->load->helper('language');
		}

		//Build WebPage Title and Breadcrume
		$_SESSION['page_title'] = null;

	}

	public function load_clean_view($page, $data = [])
	{
		$layout = $this->_layout = "{$this->_container}/{$this->_theme}/layout_clean";
		$this->load_view($page, $data, $layout);
	}
	public function load_view($page, $data = [], $layout = null)
	{
		//modify default layout after constructing the controller
		if(empty($layout)){
			$layout = $this->_layout;
		}

		$data['page'] = $page;
		$data['module'] = $this->_theme;
		$this->load->view($layout, $data);
	}
	public function json_response($data)
	{
		header('Content-Type: application/json');

		echo(json_encode($data));
		die();
	}

	public function upload_image($relative_path, $desired_file_name = NULL, $delete_original = TRUE, $field_name = 'userfile', $resolution = [200, 200], $preserve_type = FALSE, $upload_config = NULL)
	{
		if (empty($_FILES[$field_name]['name'])) {
			return FALSE;
		}

		try
		{
			//APPPATH
			//FCPATH
			$file_path = FCPATH.$relative_path;
			if (!file_exists($file_path))
				mkdir($file_path, 0755, true);

			if ($upload_config == NULL){
				$config['allowed_types']		= 'gif|jpg|png|jpeg|svg|pdf';
				$config['max_size']					= 10048; //2MB (PHP Max in this config)
				$config['max_width']				= 0; // no size restriction
				$config['max_height']				= 0; // no size restriction
			} else {
				$config = $upload_config;
			}

			$config['upload_path']		  = $file_path;
			$config['remove_spaces']  = true;
			$config['detect_mime']   = true;

			if($desired_file_name)
			{
				$config['file_name'] = $desired_file_name;
				$config['overwrite'] = true;
			} else {
				$config['encrypt_name'] = true;
			}

			//initialize in second line in case you want to do multiple uploads on same instance
			$this->load->library('upload');
			$this->upload->initialize($config);
			if ($this->upload->do_upload($field_name))
			{
				//$this->session->set_flashdata('message', 'Se subió el archivo');
				$this->session->set_flashdata('message', 'Imagen agregada correctamente');
				$this->session->set_flashdata('message_kind', 'success');

				$upload_data = $this->upload->data();

				$original_file_path = $upload_data['full_path'];
				$file_ext = $upload_data['file_ext'];
				$file_type = $upload_data['file_type'];

				if (!$preserve_type){
					$file_ext = '.jpg';
					$file_type = 'image/jpeg';
				}

				$new_file_name = $upload_data['raw_name'].$file_ext;
				$new_file_path = $file_path.$new_file_name;


				if (!$preserve_type){
					//advanced convert to JPG that sets background to white
					$input_image = imagecreatefromstring(file_get_contents($original_file_path));
					list($width, $height) = getimagesize($original_file_path);
					$output_image = imagecreatetruecolor($width, $height);
					$white = imagecolorallocate($output_image,  255, 255, 255);
					imagefilledrectangle($output_image, 0, 0, $width, $height, $white);
					imagecopy($output_image, $input_image, 0, 0, 0, 0, $width, $height);
					imagejpeg($output_image, $new_file_path);
				}

				$new_file_thumb = '';
				if ($file_type == 'image/jpeg' || $file_type == 'image/png')
				{
					//create thumbnail
					$new_file_thumb = $upload_data['raw_name'].'_thumb'.$file_ext;
					$img_config['image_library']  = 'gd2';
					$img_config['source_image']   = $new_file_path;
					$img_config['create_thumb']   = TRUE;
					$img_config['maintain_ratio'] = TRUE;
					$img_config['width']		  = $resolution[0];
					$img_config['height']		 = $resolution[1];
					$this->load->library('image_lib', $img_config);
					$this->image_lib->resize();
				}


				//return thumbnail name and image_name
				$v = '';
				if ($desired_file_name) {
					$v = dechex(time());
					$v = "?v=$v";
				}

				if (strpos($relative_path, '/') !== 0) {
					$relative_path = "/$relative_path";
				}

				$return_data['fullsize_name'] = $new_file_name . $v;
				$return_data['fullsize_type'] = $file_type;
				$return_data['fullsize_url']  = $relative_path . $new_file_name . $v;

				$return_data['original_link'] = $upload_data['full_path'];
				$return_data['original_name'] = $upload_data['file_name'] . $v;

				$return_data['thumb_name']	  = $new_file_thumb . $v;
				$return_data['thumb_url']     = $relative_path . $new_file_thumb . $v;
				
				$return_data['thumb_image_name'] = $return_data['thumb_name'];
				$return_data['image_name'] = $return_data['fullsize_name'];
				
				

				//delete original image in case its not a jpg
				if($delete_original && !$preserve_type && $upload_data['file_ext'] != '.jpg')
				{
					//delete
					unlink($upload_data['full_path']);
				}
				return $return_data;

			} else {
				$this->session->set_flashdata('message', $this->upload->display_errors());//'Error al subir imagen');
				$this->session->set_flashdata('message_kind', 'error');
				log_message('error', "Error uploading($relative_path): ".json_encode($this->upload->display_errors()));
				//$this->error = $this->upload->display_errors();
			}
		} catch ( Exception $e ) {
			$this->session->set_flashdata('message', 'Error al subir imagen');
			$this->session->set_flashdata('message_kind', 'error');
			log_message('error', "Error uploading($file_path): ".$e->getMessage());
		}
		return false;
	}


	public function display_image($file_path)
	{
		$filename = basename($file_path);
		if (file_exists($file_path) && !empty($filename))
		{
			header('Content-Type: image/jpeg');
			header('Cache-Control: private');
			header("Content-Disposition: inline; filename='$filename'");

			readfile($file_path);
		} else {
			header('Content-Type: image/png');	echo("\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x0d\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x01\x03\x00\x00\x00\x25\xdb\x56\xca\x00\x00\x00\x03\x50\x4c\x54\x45\x00\x00\x00\xa7\x7a\x3d\xda\x00\x00\x00\x01\x74\x52\x4e\x53\x00\x40\xe6\xd8\x66\x00\x00\x00\x0a\x49\x44\x41\x54\x08\xd7\x63\x60\x00\x00\x00\x02\x00\x01\xe2\x21\xbc\x33\x00\x00\x00\x00\x49\x45\x4e\x44\xae\x42\x60\x82");
		}
	}
}
