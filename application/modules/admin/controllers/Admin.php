<?php

class Admin extends Admin_Controller {

	function __construct() {
		parent::__construct();

/*
		$this->load->model(array('admin/user'));
		$this->load->model(array('admin/product'));
		$this->load->model(array('admin/retailer'));
		$this->load->model(array('admin/product_order'));
*/

	}

	public function index() {

		//$data['stations_count'] = $this->station->get_count_stations();
/*
		$users = $this->user->get_all();
		$data['users'] = $users;
*/


/*
		$product_orders = $this->product_order->count_product_orders();
		$data['product_orders'] = $product_orders;
*/

/*
		$countChats = $this->product_order->count_chats();
		$data['countChats'] = $countChats;
*/
/*
		$product_orders = $this->product_order->get_all();
		$data['product_orders'] = $product_orders;
*/

/*
		$products = $this->product->get_matched();
		$data['products'] = $products;

		$getCategorySearch = $this->product->get_category_search();
		$data['getCategorySearch'] = $getCategorySearch;

		$retailers = $this->retailer->get_all();
		$data['retailers'] = $retailers;

		$getRetailersSales = $this->product_order->get_sales_by_retailer();
		$data['getRetailersSales'] = $getRetailersSales;

		$getGenreSales = $this->product_order->get_sales_by_genre();
		$data['getGenreSales'] = $getGenreSales;

		$getCardSales = $this->product_order->get_sales_by_card();
		$data['getCardSales'] = $getCardSales;

		$getCheckoutRetailer = $this->retailer->get_checkout_retailers();
		$data['getCheckoutRetailer'] = $getCheckoutRetailer;

		$getActiveUsers = $this->user->get_active_users();
		$data['getActiveUsers'] = $getActiveUsers;

		$getCheckoutVs = $this->retailer->get_checkout_vs();
		$data['getCheckoutVs'] = $getCheckoutVs;

		$getInactiveUsers = $this->user->get_inactive_users();
		$data['getInactiveUsers'] = $getInactiveUsers;

		$getTopMatchedProducts = $this->user->get_top_matched_products();
		$data['getTopMatchedProducts'] = $getTopMatchedProducts;

		$getPurchaseChats = $this->product_order->get_purchase_chats();
		$data['getPurchaseChats'] = $getPurchaseChats;

		$getTrivialChats = $this->product_order->get_trivial_chats();
		$data['getTrivialChats'] = $getTrivialChats;

		$getSalesByRegion = $this->product_order->get_sales_by_region();
		$data['getSalesByRegion'] = $getSalesByRegion;

		$getLastChatId = $this->user->get_last_chat_id();
		$data['getLastChatId'] = $getLastChatId;

		$getLastChat = $this->user->get_last_chat($getLastChatId['chat_id']);
		$data['getLastChat'] = $getLastChat;

		$getLastUsers = $this->user->get_last_users();
		$data['getLastUsers'] = $getLastUsers;
*/

		$this->load_view('dashboard');
	}
}
