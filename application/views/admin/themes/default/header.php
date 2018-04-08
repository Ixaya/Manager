<!DOCTYPE html>
<html lang="en">

    <head>

<!--         <link rel="shortcut icon" href="/assets/frontend/images/favicon.ico"> -->
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="author" content="">

        <title>IxayaNet - Admin</title>

        <!-- Bootstrap Core CSS -->
        <link href="<?= base_url() ?>assets/admin/css/bootstrap.min.css" rel="stylesheet">

        <!-- MetisMenu CSS -->
        <link href="<?= base_url() ?>assets/admin/css/metisMenu.min.css" rel="stylesheet">

        <!-- DataTables CSS -->
        <link href="<?= base_url() ?>assets/admin/css/dataTables.bootstrap.css" rel="stylesheet">

        <!-- DataTables Responsive CSS -->
        <link href="<?= base_url() ?>assets/admin/css/dataTables.responsive.css" rel="stylesheet">
        
        <!-- DataTables Treetable CSS -->
        <link href="<?= base_url() ?>assets/admin/css/jquery.treetable.css" rel="stylesheet">
        <link href="<?= base_url() ?>assets/admin/css/treeTable.bootstrap.css" rel="stylesheet">


        <!-- Custom CSS -->
        <link href="<?= base_url() ?>assets/admin/css/sb-admin-2.css" rel="stylesheet">

        <!-- Custom Fonts -->
        <link href="<?= base_url() ?>assets/admin/css/font-awesome.min.css" rel="stylesheet" type="text/css">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css" rel="stylesheet">

        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->

    </head>

    <body>

        <div id="wrapper">

            <!-- Navigation -->
            <nav class="navbar navbar-default navbar-static-top" role="navigation" style="margin-bottom: 0">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                        <span class="sr-only">Toggle navigation</span>

                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>

                    </button>
    	            <img class="navbar-brand" src="<?= base_url() ?>assets/frontend/images/IxayaNet_logo.png" />
					<a class="navbar-brand" href="<?= base_url('admin/dashboard') ?>">Welcome <?=$this->logged_in_name?></a></div>
                </div>

                <!-- /.navbar-header -->

                <ul class="nav navbar-top-links navbar-right">
                    <li class="dropdown">
<!--
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-envelope fa-fw"></i>  <i class="fa fa-caret-down"></i>
                        </a>
-->
<!--
                        <ul class="dropdown-menu dropdown-messages">
                            <li>
                                <a href="#">
                                    <div>
                                        <strong>John Smith</strong>
                                        <span class="pull-right text-muted">
                                            <em>Yesterday</em>
                                        </span>
                                    </div>
                                    <div>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque eleifend...</div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <strong>John Smith</strong>
                                        <span class="pull-right text-muted">
                                            <em>Yesterday</em>
                                        </span>
                                    </div>
                                    <div>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque eleifend...</div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <strong>John Smith</strong>
                                        <span class="pull-right text-muted">
                                            <em>Yesterday</em>
                                        </span>
                                    </div>
                                    <div>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque eleifend...</div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a class="text-center" href="#">
                                    <strong>Read All Messages</strong>
                                    <i class="fa fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
-->
                        <!-- /.dropdown-messages -->
                    </li>
                    <!-- /.dropdown -->
                    <li class="dropdown">
<!--
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-tasks fa-fw"></i>  <i class="fa fa-caret-down"></i>
                        </a>
-->
<!--
                        <ul class="dropdown-menu dropdown-tasks">
                            <li>
                                <a href="#">
                                    <div>
                                        <p>
                                            <strong>Task 1</strong>
                                            <span class="pull-right text-muted">40% Complete</span>
                                        </p>
                                        <div class="progress progress-striped active">
                                            <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 40%">
                                                <span class="sr-only">40% Complete (success)</span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <p>
                                            <strong>Task 2</strong>
                                            <span class="pull-right text-muted">20% Complete</span>
                                        </p>
                                        <div class="progress progress-striped active">
                                            <div class="progress-bar progress-bar-info" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100" style="width: 20%">
                                                <span class="sr-only">20% Complete</span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <p>
                                            <strong>Task 3</strong>
                                            <span class="pull-right text-muted">60% Complete</span>
                                        </p>
                                        <div class="progress progress-striped active">
                                            <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 60%">
                                                <span class="sr-only">60% Complete (warning)</span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <p>
                                            <strong>Task 4</strong>
                                            <span class="pull-right text-muted">80% Complete</span>
                                        </p>
                                        <div class="progress progress-striped active">
                                            <div class="progress-bar progress-bar-danger" role="progressbar" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100" style="width: 80%">
                                                <span class="sr-only">80% Complete (danger)</span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a class="text-center" href="#">
                                    <strong>See All Tasks</strong>
                                    <i class="fa fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
-->
                        <!-- /.dropdown-tasks -->
                    </li>
                    <!-- /.dropdown -->
                    <li class="dropdown">
<!--
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-bell fa-fw"></i>  <i class="fa fa-caret-down"></i>
                        </a>
-->
<!--
                        <ul class="dropdown-menu dropdown-alerts">
                            <li>
                                <a href="#">
                                    <div>
                                        <i class="fa fa-comment fa-fw"></i> New Comment
                                        <span class="pull-right text-muted small">4 minutes ago</span>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <i class="fa fa-twitter fa-fw"></i> 3 New Followers
                                        <span class="pull-right text-muted small">12 minutes ago</span>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <i class="fa fa-envelope fa-fw"></i> Message Sent
                                        <span class="pull-right text-muted small">4 minutes ago</span>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <i class="fa fa-tasks fa-fw"></i> New Task
                                        <span class="pull-right text-muted small">4 minutes ago</span>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#">
                                    <div>
                                        <i class="fa fa-upload fa-fw"></i> Server Rebooted
                                        <span class="pull-right text-muted small">4 minutes ago</span>
                                    </div>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a class="text-center" href="#">
                                    <strong>See All Alerts</strong>
                                    <i class="fa fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
-->
                        <!-- /.dropdown-alerts -->
                    </li>
                    <!-- /.dropdown -->
                    <li class="dropdown">
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-user fa-fw"></i>  <i class="fa fa-caret-down"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-user">
                            <li><a><i class="fa fa-language fa-fw"></i>
                            <select onchange="javascript:window.location.href='<?php echo base_url(); ?>language/change/'+this.value;">
							    <option value="english" <?php if($this->session->userdata('language') == 'english') echo 'selected="selected"'; ?>>English</option>
							    <option value="spanish" <?php if($this->session->userdata('language') == 'spanish') echo 'selected="selected"'; ?>>Spanish</option>
							</select>
							</a></li>
<!--
                            <li><a href="#"><i class="fa fa-user fa-fw"></i> User Profile</a></li>
                            <li><a href="<?= base_url('admin/clients') ?>"><i class="fa fa-gear fa-fw"></i> Settings</a></li>
-->
                            <li class="divider"></li>
                            <li><a href="<?=  base_url('auth/logout')?>"><i class="fa fa-sign-out fa-fw"></i> Logout</a></li>
                        </ul>
                        <!-- /.dropdown-user -->
                    </li>
                    <!-- /.dropdown -->
                </ul>
                <!-- /.navbar-top-links -->

                <div class="navbar-default sidebar" role="navigation">
                    <div class="sidebar-nav navbar-collapse">
                        <ul class="nav" id="side-menu">
	                        
<!-- 	                        Search Menu -->

                            <li class="sidebar-search">
                            <!--
                                <div class="input-group custom-search-form">
                                    <input type="text" class="form-control" placeholder="Search...">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" type="button">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </span>
                                </div>
                                -->
                                <!-- /input-group -->
                            </li>

                            
                            
                            
                            <li><a href="<?= base_url('admin/dashboard') ?>"><i class="fa fa-dashboard fa-fw"></i> Dashboard</a></li>
                            <li><a href="#"><i class="fa fa-institution fa-fw"></i> Retailers<span class="fa arrow"></span></a>
	                            <ul class="nav nav-second-level collapse">
		                            <!-- /.nav-second-level -->
	                            	<li><a href="<?= base_url('admin/retailers') ?>"><i class="fa fa-shopping-cart fa-fw"></i> Retailers</a></li>
			                        <li><a href="<?= base_url('admin/retailer_category_urls') ?>"><i class="fa fa-list-ul fa-fw"></i> Retailer URLs</a></li>
			                        <li><a href="<?= base_url('admin/products') ?>"><i class="fa fa-archive fa-fw"></i> Products</a></li>
			                        <li><a href="<?= base_url('admin/sync_statuses') ?>"><i class="fa fa-refresh fa-spin fa-fw"></i> Sync Status</a></li>
<!-- 			                        <li><a href="<?= base_url('admin/retailer_payments') ?>"><i class="fa fa fa-usd fa-fw"></i>Retailer Payments</a></li>                             -->
		                        </ul>
                            </li>
                            
                            <li><a href="#"><i class="fa fa-user fa-fw"></i> Users / Profiles<span class="fa arrow"></span></a>
	                            <ul class="nav nav-second-level collapse">
		                            <!-- /.nav-second-level -->
		                            <li><a href="<?= base_url('admin/sysusers') ?>"><i class="fa fa-user fa-fw"></i>Users</a></li>
									<li><a href="<?= base_url('admin/user_friends') ?>"><i class="fa fa-users fa-fw"></i>Friends</a></li>
		                            <!-- <li><a href="<?= base_url('admin/user-groups') ?>"><i class="fa fa-users fa-fw"></i>Groups</a></li> -->
		                            <li><a href="<?= base_url('admin/addresses') ?>"><i class="fa fa-map-marker fa-fw"></i> Addresses</a></li>
		                            <li><a href="<?= base_url('admin/payment_methods') ?>"><i class="fa fa-credit-card fa-fw"></i> Cards</a></li>
		                            <li><a href="<?= base_url('admin/user_lists') ?>"><i class="fa fa-heart-o fa-fw"></i> Lists</a></li>
		                            <!-- <li><a href="<?= base_url('admin/user_social_networks') ?>"><i class="fa fa-thumbs-o-up fa-fw"></i> Social Networks</a></li> -->
		                            <li><a href="<?= base_url('admin/product_favorites') ?>"><i class="fa fa-star fa-fw"></i>Favorite Products</a></li>
		                            <li><a href="<?= base_url('admin/notification_schedules') ?>"><i class="fa fa-envelope fa-fw"></i>Notifications schedule</a></li>
		                        </ul>
                            </li>

                
                
                
							<li><a href="#"><i class="fa fa-comments-o fa-fw"></i> Chat<span class="fa arrow"></span></a>
	                            <ul class="nav nav-second-level collapse">
		                            <!-- /.nav-second-level -->
		                            <li><a href="<?= base_url('admin/chats') ?>"><i class="fa fa-comment fa-fw"></i> Conversations</a></li>
<!-- 		                            <li><a href="<?= base_url('admin/keywords') ?>"><i class="fa fa-comment fa-fw"></i> Keywords</a></li> -->
		                        </ul>
                            </li>
                            
							<li><a href="#"><i class="fa fa-money fa-fw"></i> Sales<span class="fa arrow"></span></a>
	                            <ul class="nav nav-second-level collapse">
		                            <!-- /.nav-second-level -->
		                            <li><a href="<?= base_url('admin/product_orders') ?>"><i class="fa fa-file-text-o fa-fw"></i> Orders</a></li>
<!-- 		                            <li><a href="<?= base_url('admin/transactions') ?>"><i class="fa fa fa-exchange fa-fw"></i>Transactions</a></li> -->
		                            <li><a href="<?= base_url('admin/subscribers') ?>"><i class="fa fa fa-envelope-o fa-fw"></i>Subscribers</a></li>
		                        </ul>
                            </li>
                            

                            <!--
<?php if ($this->is_admin): ?>
                            <li><i class="fa fa-lock fa-fw"></i> System Management</li>
                            
                            
                            <?php endif; ?>
-->
                        </ul>
                    </div>
                    <!-- /.sidebar-collapse -->
                </div>
                <!-- /.navbar-static-side -->
            </nav>