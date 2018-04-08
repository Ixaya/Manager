<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.1/morris.css">
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/raphael/2.1.0/raphael-min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.1/morris.min.js"></script>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
<!--             <h1 class="page-header"><img height="64px" src="<?= base_url() ?>assets/admin/images/cooknserve_logo.png" /> | Dashboard</h1> -->
<!-- 				 <img height="64px" src="<?= base_url() ?>assets/admin/images/cooknserve_logo.png" /> -->
<!--             <h1 class="page-header"><i class="fa fa-dashboard fa-fw"></i> Dashboard</a></li></h1> -->
            <h1 class="page-header"><i class="fa fa-dashboard fa-fw"></i><?= $this->lang->line('admin_dashboard'); ?></h1>


        </div>
        <!-- /.col-lg-12 -->
    </div>
    <!-- /.row -->
    <div class="row">
<!--         <?php if ($this->session->flashdata('message')): ?> -->
        <div class="col-lg-12 col-md-12">
            <div class="alert alert-info alert-dismissable">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <?=$this->session->flashdata('message')?>
            </div>
        </div>
        <?php endif; ?>
        
<!--         retailers -->
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-institution fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo(count($retailers))?></div>
                            <div><?= $this->lang->line('admin_retailers_header'); ?> </div>
                        </div>
                    </div>
                </div>
                <a href="#">
                    <div class="panel-footer">
                        <span class="pull-left"><a href="<?= base_url('admin/retailers/') ?>"><?= $this->lang->line('admin_retailers_description'); ?></a></span>
                        <span class="pull-right"><a href="<?= base_url('admin/retailers/') ?>"><i class="fa fa-arrow-circle-right"></i></a></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
<!-- products -->        
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-yellow">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-archive fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo(count($products))?></div>
                            <div>Züggig products</div>
                        </div>
                    </div>
                </div>
                <a href="#">
                    <div class="panel-footer">
                        <span class="pull-left"><a href="<?= base_url('admin/products/') ?>">View products</a></span>
                        <span class="pull-right"><a href="<?= base_url('admin/products/') ?>"><i class="fa fa-arrow-circle-right"></i></a></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
<!-- users -->        
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-red">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-user fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo(count($users))?></div>
                            <div>Users</div>
                        </div>
                    </div>
                </div>
                <a href="#">
                    <div class="panel-footer">
                        <span class="pull-left"><a href="<?= base_url('admin/sysusers/') ?>">View users</a></span>
                        <span class="pull-right"><a href="<?= base_url('admin/sysusers/') ?>"><i class="fa fa-arrow-circle-right"></i></a></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        
<!-- orders -->        
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-green">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-usd fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo($product_orders)?></div>
                            <div>Orders</div>
                        </div>
                    </div>
                </div>
                <a href="#">
                    <div class="panel-footer">
                        <span class="pull-left"><a href="<?= base_url('admin/product_orders/') ?>">View orders</a></span>
                        <span class="pull-right"><a href="<?= base_url('admin/product_orders/') ?>"><i class="fa fa-arrow-circle-right"></i></a></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        
<!-- chats -->
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-purple">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-comments-o fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo($countChats)?></div>
                            <div>Chats</div>
                        </div>
                    </div>
                </div>
                <a href="#">
                    <div class="panel-footer">
                        <span class="pull-left"><a style="color: #6a47aa" href="<?= base_url('admin/chats/') ?>">View chats</a></span>
                        <span class="pull-right"><a href="<?= base_url('admin/chats/') ?>"><i class="fa fa-arrow-circle-right" style="color: #333"></i></a></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        
        <!--
<?php for ($i = 0; $i < count($getCheckoutVs)-1; $i++) {
				    echo($getCheckoutVs[$i]['total']." " );
				}
				echo($getCheckoutVs[count($getCheckoutVs)-1]['total']." " );
	        
	        
	         ?>
-->
        
    </div>
    
    <div class="row">
	    <div class="col-lg-6">
		    
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Active users (Last 30 days)</h3>
				</div>
	    
				<div class="panel-body">
	    
			        <h3><i class='fa fa-apple fa-2x' aria-hidden='true'></i> Apple: <?php echo($getActiveUsers[1]['total']); ?></h3>
			        
			        <h3><i class='fa fa-android fa-2x' aria-hidden='true'></i> Android: <?php echo($getActiveUsers[2]['total']); ?></h3>
			        
			        <h3><i class='fa fa-globe fa-2x' aria-hidden='true'></i> Web: <?php echo($getActiveUsers[0]['total']); ?></h3>
	    		</div>
			</div>
	    </div>
	    

	    <div class="col-lg-6">
		    
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Inactive users (Last 30 days)</h3>
				</div>
	    
				<div class="panel-body">

	    
			        <h3><i class='fa fa-apple fa-2x' aria-hidden='true'></i> Apple: <?php echo($getInactiveUsers[1]['total']); ?></h3>
			        
			        <h3><i class='fa fa-android fa-2x' aria-hidden='true'></i> Android: <?php echo($getInactiveUsers[2]['total']); ?></h3>
			        
			        <h3><i class='fa fa-globe fa-2x' aria-hidden='true'></i> Web: <?php echo($getInactiveUsers[0]['total']); ?></h3>
	    		</div>
			</div>
	    </div>


					<!-- <h3>Available Zuggig products: <?php echo($getZuggigProducts['zuggigProducts']); ?></h3> -->
    </div>
<!--     Usuarios activos texto -->
    
    <div class="row">
	    <div class="col-lg-6">
		    
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Active users (Last 30 days)</h3>
				</div>
	    
				<div class="panel-body">
					<!-- <h3>Available Zuggig products: <?php echo($getZuggigProducts['zuggigProducts']); ?></h3> -->
	    
						<div id="activeUsers"></div>
	    		</div>
			</div>
	    </div>
	    

	    <div class="col-lg-6">
		    
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Inactive users (Last 30 days)</h3>
				</div>
	    
				<div class="panel-body">
					<div id="inactiveUsers"></div>
	    		</div>
			</div>
	    </div>


					<!-- <h3>Available Zuggig products: <?php echo($getZuggigProducts['zuggigProducts']); ?></h3> -->
    </div>
<!--     Usuarios activos graficos -->
    
    <div class="row">
	    <div class="col-lg-12">
	    	<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Last 10 registred users</h3>
				</div>
	    
				<div class="panel-body">
					<div class="dataTable_wrapper">
                        <table class="table table-striped table-bordered table-hover" id="dataTables">
                            <thead>
	                            <tr>
		                            <th>ID</th>
		                            <th>Name</th>
		                            <th>Email</th>
		                            <th>Date created</th>
		                            <th>Gender</th>
		                            <th>Action</th>
	                            </tr>
                            </thead>
                            
                            <tbody>
	                            <?php foreach($getLastUsers as $lastUser): ?>
		                            <tr>
			                            <td><?=$lastUser['id']?></td>
			                            <td><?=$lastUser['first_name']." ".$lastUser['last_name']?></td>
			                            <td><?=$lastUser['email']?></td>
			                            <td><?=$lastUser['last_activity_date']?></td>
			                            <td><?=$lastUser['gender']?></td>
			                            <td><a href="<?= base_url('admin/sysusers/detail/'.$lastUser['id']) ?>" class="btn btn-primary">details</a></td>
		                            </tr>
	                            <?php endforeach;?>
                            </tbody>
                            
                            <tfoot>
	                            <tr>
		                            <th>ID</th>
		                            <th>Name</th>
		                            <th>Email</th>
		                            <th>Date created</th>
		                            <th>Gender</th>
		                            <th>Action</th>
	                            </tr>
                            </tfoot>
                        </table>
					</div>
	    		</div>
			</div>
	    </div>
    	
    </div>
<!--     Usuarios registrados -->

	<div class="row">
	    <div class="col-lg-12">
	    	<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Top 10 matched products</h3>
				</div>
	    
				<div class="panel-body">
					<div class="dataTable_wrapper">
                        <table class="table table-striped table-bordered table-hover" id="dataTables">
                            <thead>
	                            <tr>
		                            <th>ID</th>
		                            <th>Image</th>
		                            <th>Name</th>
		                            <th>Total</th>
		                            <th>Action</th>
	                            </tr>
                            </thead>
                            
                            <tbody>
	                            <h3> Available Zuggig products: <?php echo($getZuggigProducts['zuggigProducts']); ?> </h3>
	                            
	                            <?php foreach($getTopMatchedProducts as $topProduct): ?>
		                            <tr>
			                            <td><?=$topProduct['product_id']?></td>
			                            <td><img width="50px" src="<?=$topProduct['image_url'];?>"></td>
			                            <td><?=$topProduct['product_name']?></td>
			                            <td><?=$topProduct['total']?></td>
			                            <td><a href="<?= base_url('admin/product_details/index_selected/'.$topProduct['product_id']) ?>" class="btn btn-primary">details</a></td>
		                            </tr>
	                            <?php endforeach;?>
                            </tbody>
                            
                            <tfoot>
	                            <tr>
		                            <th>ID</th>
		                            <th>Image</th>
		                            <th>Name</th>
		                            <th>Total</th>
		                            <th>Action</th>
	                            </tr>
                            </tfoot>
                        </table>
					</div>
	    		</div>
			</div>
	    </div>
    	
    </div>
<!-- 	productos matcheados -->

    <div class="row">
	    <div class="col-lg-12">
	    	<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Checkout vs non-checkout orders</h3>
				</div>
	    
				<div class="panel-body">
					<div class="col-md-6">
						<div class="dataTable_wrapper">
	                        <table class="table table-striped table-bordered table-hover" id="dataTables">
	                            <thead>
		                            <tr>
			                            <th>Retailer</th>
			                            <th>Status</th>
			                            <th>Total orders</th>
		                            </tr>
	                            </thead>
	                            
	                            <tbody>
		                            <?php foreach($getCheckoutVs as $vs): ?>
			                            <tr>
				                            <td><?=$vs['title']?></td>
				                            <td><?php if($vs['status'] == 2){
					                            echo("<p style='color:green'>Succes</p>");
				                            }if($vs['status'] == 1){
					                            echo("<p style='color:orange'>Processing</p>");
				                            }if($vs['status'] == -1){
					                            echo("<p style='color:red'>Error</p>");
				                            }
					                            
				                            ?></td>
				                            <td><?=$vs['total']?></td>
			                            </tr>
		                            <?php endforeach;?>
	                            </tbody>
	                            
	                            <tfoot>
		                            <tr>
			                            <th>Retailer</th>
			                            <th>Status</th>
			                            <th>Total orders</th>
		                            </tr>
	                            </tfoot>
	                        </table>
						</div>
					</div>
					
					<div class="col-md-6">
						<div id="checkoutVs"></div>	
				    </div>
	    		</div>
			</div>
	    </div>
    	
    </div>
<!--     chackout vs non-checkout -->
    
    <div class="row">
	    <div class="col-md-6">
		    <div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Top 5 category search (share)</h3>
				</div>
	    
				<div class="panel-body">
					<div id="categorySearch"></div>
	    		</div>
			</div>
	    	
	    </div>
	    
	    
	    <div class="col-md-6">
	    	
	    </div>
    	
    </div>
<!--     Busquedas -->
    
    <div class="row">
	    <div class="col-md-6">
		    <div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Checkout (Retailer percentage)</h3>
				</div>
	    
				<div class="panel-body">
					<div id="checkoutRetailer"></div>
	    		</div>
			</div>
	    	
	    </div>
	    
	    
	    <div class="col-md-6">
	    	
	    </div>
    	
    </div>
<!--     Checkouts -->
    
    <div class="row">
	    <div class="col-lg-6">
		    
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Sales by Retailer (MXN.)</h3>
				</div>
	    
				<div class="panel-body">
					<div id="retailerSales"></div>
	    		</div>
			</div>
	    </div>
    
	    <div class="col-lg-6">
	    	<div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Sales by Gender (MXN.)</h3>
				</div>
	    
				<div class="panel-body">
		    		<div id="GenreSales"></div>
				</div>
			</div>
	    </div>
    
	    <div class="col-lg-6">
		    <div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Sales by Card Type (MXN.)</h3>
				</div>
	    
				<div class="panel-body">
					<div id="cardType"></div>		    
				</div>
			</div>
	    </div>
	    
	    <div class="col-lg-6">
		    <div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Trivial chats</h3>
				</div>
	    
				<div class="panel-body">
					<div id="trivialChats"></div>		    
				</div>
			</div>
	    </div>
	    
	    <div class="col-lg-6">
		    <div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Sales by state (MXN.)</h3>
				</div>
	    
				<div class="panel-body">
					<div id="regionSales"></div>		    
				</div>
			</div>
	    </div>
	    
	    <div class="col-lg-6">
		    <div class="panel panel-default">
				<div class="panel-heading">
					<h3 style="color:#00317c">Last chat</h3>
				</div>
				
				<div class="panel-body">
					<ul class="chat">
						<?php foreach($getLastChat as $chat){
							
							if ($chat['user_id'] == 0){	?>
							
								<li class="left clearfix">
                                	<span class="chat-img pull-left">
                                        <img width="50px" src="/assets/frontend/images/logo.png" alt="User Avatar" class="img-circle">
                                    </span>
                                    <div class="chat-body clearfix">
                                        <div class="header">
                                            <strong class="primary-font">Zuggig</strong>
                                            
                                        </div>
                                        <p>
                                            <?=$chat['message']; ?>
                                        </p>
                                    </div>
                                </li>
                                
							<?php } else {?>
							
								<li class="left clearfix">
                                	<span class="chat-img pull-left">
                                        <img width="50px" src="<?=$chat['image_url'];?>" alt="User Avatar" class="img-circle">
                                    </span>
                                    <div class="chat-body clearfix">
                                        <div class="header">
                                            <strong class="primary-font"><?=$chat['first_name']." ".$chat['last_name']; ?></strong>
                                            
                                        </div>
                                        <p >
                                            <?=$chat['message']; ?>
                                        </p>
                                    </div>
                                </li>
							
					<?php	}
						} ?>
					</ul>
				</div>
			</div>
	    </div>
    </div>
<!--     ventas -->



</div>

<script>
		
		new Morris.Bar({
	  // ID of the element in which to draw the chart.
	  element: 'retailerSales',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
	  data: [
	    { x: 'Retailer', a: <?=$getRetailersSales[0]['total'];?>, b: <?=$getRetailersSales[1]['total'];?>, c: <?=$getRetailersSales[2]['total'];?>, d: <?=$getRetailersSales[3]['total'];?>, e: <?=$getRetailersSales[4]['total'];?> }
	  ],
	  // The name of the data record attribute that contains x-values.
	  xkey: 'x',
	  ykeys: ['a', 'b', 'c', 'd', 'e'],
	  labels: ['Liverpool', 'BestBuy', 'ClaroShop', 'Innovasport', 'Petco']
	});
	
	new Morris.Bar({
	  // ID of the element in which to draw the chart.
	  element: 'GenreSales',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
	  data: [
	    { x: 'Gender', a: <?=$getGenreSales[0]['total'];?>, b: <?=$getGenreSales[1]['total'];?> }
	  ],
	  // The name of the data record attribute that contains x-values.
	  xkey: 'x',
	  ykeys: ['a', 'b'],
	  labels: ['Male', 'Female']
	});
	
	new Morris.Bar({
	  // ID of the element in which to draw the chart.
	  element: 'cardType',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
	  data: [
	    { x: 'Card Type', a: <?=$getCardSales[2]['total'];?>, b: <?=$getCardSales[0]['total'];?>, c: <?=$getCardSales[1]['total'];?>}
	  ],
	  // The name of the data record attribute that contains x-values.
	  xkey: 'x',
	  ykeys: ['a', 'b', 'c'],
	  labels: ['Amex', 'Visa', 'Mastercard']
	});
	
	new Morris.Bar({
	  // ID of the element in which to draw the chart.
	  element: 'activeUsers',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
	  data: [
	    { x: 'Os Kind', a: <?php echo($getActiveUsers[1]['total']); ?>, b: <?php echo($getActiveUsers[2]['total']); ?>, c: <?php echo($getActiveUsers[0]['total']); ?>}
	  ],
	  // The name of the data record attribute that contains x-values.
	  xkey: 'x',
	  ykeys: ['a', 'b', 'c'],
	  labels: ['Apple', 'Andorid', 'Web']
	});
	
	new Morris.Bar({
	  // ID of the element in which to draw the chart.
	  element: 'inactiveUsers',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
	  data: [
	    { x: 'Os Kind', a: <?php echo($getInactiveUsers[1]['total']); ?>, b: <?php echo($getInactiveUsers[2]['total']); ?>, c: <?php echo($getInactiveUsers[0]['total']); ?>}
	  ],
	  // The name of the data record attribute that contains x-values.
	  xkey: 'x',
	  ykeys: ['a', 'b', 'c'],
	  labels: ['Apple', 'Andorid', 'Web']
	});
	
	new Morris.Donut({
	  // ID of the element in which to draw the chart.
	  element: 'trivialChats',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
data: [
    {label: "Sales chats", value: <?=$getPurchaseChats?>},
    {label: "Trivial chats", value: <?=$getTrivialChats-$getPurchaseChats?>}
  ]	});
  
  new Morris.Bar({
	  // ID of the element in which to draw the chart.
	  element: 'regionSales',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
	  data: [
	    { x: 'States', a: <?php echo($getSalesByRegion[0]['total']); ?>, b: <?php echo($getSalesByRegion[1]['total']); ?>, c: <?php echo($getSalesByRegion[2]['total']); ?>, d: <?php echo($getSalesByRegion[3]['total']); ?>, e: <?php echo($getSalesByRegion[4]['total']); ?>}
	  ],
	  // The name of the data record attribute that contains x-values.
	  xkey: 'x',
	  ykeys: ['a', 'b', 'c', 'd', 'e'],
	  labels: ['<?php echo($getSalesByRegion[0]['state']); ?>', '<?php echo($getSalesByRegion[1]['state']); ?>', '<?php echo($getSalesByRegion[2]['state']); ?>', '<?php echo($getSalesByRegion[3]['state']); ?>', '<?php echo($getSalesByRegion[4]['state']); ?>']
	});
	
	new Morris.Donut({
	  // ID of the element in which to draw the chart.
	  element: 'checkoutRetailer',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
data: [
    {label: "<?=$getCheckoutRetailer[0]['title']?>", value: <?=$getCheckoutRetailer[0]['total']?>},
    {label: "<?=$getCheckoutRetailer[1]['title']?>", value: <?=$getCheckoutRetailer[1]['total']?>},
    {label: "<?=$getCheckoutRetailer[2]['title']?>", value: <?=$getCheckoutRetailer[2]['total']?>},
    {label: "<?=$getCheckoutRetailer[3]['title']?>", value: <?=$getCheckoutRetailer[3]['total']?>},
    {label: "<?=$getCheckoutRetailer[4]['title']?>", value: <?=$getCheckoutRetailer[4]['total']?>}
  ]	});
  
  new Morris.Donut({
	  // ID of the element in which to draw the chart.
	  element: 'categorySearch',
	  
	  resize: true,
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
data: [
    {label: "<?=$getCategorySearch[0]['kind']?>", value: <?=$getCategorySearch[0]['total']?>},
    {label: "<?=$getCategorySearch[1]['kind']?>", value: <?=$getCategorySearch[1]['total']?>},
    {label: "<?=$getCategorySearch[2]['kind']?>", value: <?=$getCategorySearch[2]['total']?>},
    {label: "<?=$getCategorySearch[3]['kind']?>", value: <?=$getCategorySearch[3]['total']?>},
    {label: "<?=$getCategorySearch[4]['kind']?>", value: <?=$getCategorySearch[4]['total']?>}
    
  ]	});
  
  new Morris.Donut({
	  // ID of the element in which to draw the chart.
	  element: 'checkoutVs',
	  
	  resize: true,
	  
	  colors: [
	  
	  <?php for ($i = 0; $i < count($getCheckoutVs)-1; $i++) {
	  			if($getCheckoutVs[$i]['status'] == 2){
                    echo("'#17a512',");
                }if($getCheckoutVs[$i]['status'] == 1){
                    echo(" '#ffcc00',");
                }if($getCheckoutVs[$i]['status'] == -1){
                    echo("'#c40b0b',");
                };
                
	  } echo("'#c40b0b'"); ?>
	  
	  
	  ],
	  // Chart data records -- each entry in this array corresponds to a point on
	  // the chart.
data: [
	
	<?php for ($i = 0; $i < count($getCheckoutVs)-1; $i++) {
				echo("{label: '". $getCheckoutVs[$i]['title']." "."Status: ");
				
				if($getCheckoutVs[$i]['status'] == 2){
                    echo("Succes");
                }if($getCheckoutVs[$i]['status'] == 1){
                    echo("Processing");
                }if($getCheckoutVs[$i]['status'] == -1){
                    echo("Error");
                };
                                            
				echo("', value:". $getCheckoutVs[$i]['total'] ."}, ");
			}
			echo("{label: '". $getCheckoutVs[count($getCheckoutVs)-1]['title']." "."Status: Error ". "', value:". $getCheckoutVs[count($getCheckoutVs)-1]['total'] ."} ");
	         ?>
    
  ]	});
</script>


<style>
	
	.panel-purple{
	  color: #ffffff;
	  background-color: #6a47aa;
	  border-color: #6a47aa;
	}
	
</style>
<!-- /#page-wrapper -->