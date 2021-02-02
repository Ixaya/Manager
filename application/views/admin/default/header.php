<!DOCTYPE html>
<html lang="en">

	<head>

<!--		 <link rel="shortcut icon" href="/assets/frontend/images/favicon.ico"> -->
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="description" content="">
		<meta name="author" content="">

		<title>Ixaya - Admin</title>

		<!-- Bootstrap Core CSS -->
		<link href="<?= base_url() ?>assets/admin/default/css/bootstrap.min.css" rel="stylesheet">

		<!-- MetisMenu CSS -->
		<link href="<?= base_url() ?>assets/admin/default/css/metisMenu.min.css" rel="stylesheet">

		<!-- DataTables CSS -->
		<link href="<?= base_url() ?>assets/admin/default/css/dataTables.bootstrap.css" rel="stylesheet">

		<!-- DataTables Responsive CSS -->
		<link href="<?= base_url() ?>assets/admin/default/css/dataTables.responsive.css" rel="stylesheet">
		
		<!-- DataTables Treetable CSS -->
		<link href="<?= base_url() ?>assets/admin/default/css/jquery.treetable.css" rel="stylesheet">
		<link href="<?= base_url() ?>assets/admin/default/css/treeTable.bootstrap.css" rel="stylesheet">


		<!-- Custom CSS -->
		<link href="<?= base_url() ?>assets/admin/default/css/sb-admin-2.css" rel="stylesheet">

		<!-- Custom Fonts -->
		<link href="<?= base_url() ?>assets/admin/default/css/font-awesome.min.css" rel="stylesheet" type="text/css">
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
					<img class="navbar-brand" src="<?= base_url() ?>assets/admin/default/images/logo.png" />
					<a class="navbar-brand" href="<?= base_url('admin/dashboard') ?>">Welcome <?=$this->logged_in_name?></a>
				</div>

				<!-- /.navbar-header -->
<!--
				<ul class="nav navbar-top-links navbar-right">
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
							<li><a href="#"><i class="fa fa-user fa-fw"></i> User Profile</a></li>
							 <li class="divider"></li>
							<li><a href="<?=  base_url('auth/logout')?>"><i class="fa fa-sign-out fa-fw"></i> Logout</a></li>
						</ul>
					</li>
				</ul>
-->
				<!-- /.navbar-top-links -->

				<div class="navbar-default sidebar" role="navigation">
					<div class="sidebar-nav navbar-collapse">
						<ul class="nav" id="side-menu">
							
							<!-- Search Menu -->
<!--
							<li class="sidebar-search">
								<div class="input-group custom-search-form">
									<input type="text" class="form-control" placeholder="Search...">
									<span class="input-group-btn">
										<button class="btn btn-default" type="button">
											<i class="fa fa-search"></i>
										</button>
									</span>
								</div>
							</li>
-->

							
							
							
							<li><a href="<?= base_url('admin/dashboard') ?>"><i class="fa fa-dashboard fa-fw"></i> Dashboard</a></li>					  
							<li><a href="<?= base_url('admin/sysusers') ?>"><i class="fa fa-user fa-fw"></i> Users</a></li>
							<li><a href="<?= base_url('admin/webpages') ?>"><i class="fa fa-square fa-fw"></i> Pages</a></li>
							<li><a href="<?= base_url('admin/page_sections') ?>"><i class="fa fa-square fa-fw"></i> Page Sections</a></li>
							<li><a href="<?= base_url('admin/page_items') ?>"><i class="fa fa-square fa-fw"></i> Page Items</a></li>
							
<!-- Collapsable Menu Example -->
<!--
	
							<li><a href="#"><i class="fa fa-user fa-fw"></i> System Management<span class="fa arrow"></span></a>
								<ul class="nav nav-second-level collapse">
									<li><a href="<?= base_url('admin/notification_schedules') ?>"><i class="fa fa-envelope fa-fw"></i>Notifications schedule</a></li>
								</ul>
							</li>
-->
							<li><a href="<?=  base_url('auth/logout')?>"><i class="fa fa-sign-out fa-fw"></i> Logout</a></li>
						</ul>
					</div>
					<!-- /.sidebar-collapse -->
				</div>
				<!-- /.navbar-static-side -->
			</nav>