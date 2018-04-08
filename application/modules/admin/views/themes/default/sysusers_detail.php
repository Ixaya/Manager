<div id="page-wrapper">
	<div class="row">
		<div class="col-lg-12">
			<div class="page-header users-header">
	    
                <h2> Details of user
                     <a href="<?= base_url('admin/sysusers') ?>" class="btn btn-primary"> Go back to sysusers </a>
                     <?php if(isset($getFb)): ?>
					 <a href="<?= base_url('admin/sysusers/edit/'.$getFb['id']) ?>" class="btn btn-info"> Edit </a>
					 <a href="<?= base_url('admin/sysusers/delete/'.$getFb['id']) ?>" class="btn btn-danger">Delete</a>
					 <?php endif; ?>
				</h2>
                
    		</div>
		</div>
	</div>
	
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
	            <div class="panel-heading">
				
	            </div>
	            
	            <div class="panel-body">
		            <?php  if(isset($getFb)): ?>
		            <div class="row">
		            	<div class="col-md-6">
		            		<img height="185px" src="<?php echo($getFb['image_url']); ?>">
		            	</div>
		            	<div class="col-md-4">
			            	
		            		<label>Name: </label> <?php echo("  " . $getFb['first_name'] . "  " . $getFb['last_name']); ?></br>
		            		<label>E-mail: </label> <?php echo("  " . $getFb['email']); ?></br>
		            		<label>Gender: </label> <?php echo("  " . $getFb['gender']); ?></br>
							<label>Last Activity: <?= $user->last_activity_date ?> </label> </br>

		            		<?php 
			            	if(isset($user->last_activity_os)){				            	
					            if($user->last_activity_os == 2){
					            	echo("<i class='fa fa-android fa-5x' aria-hidden='true'></i> &nbsp");
				            	}elseif($user->last_activity_os == 1){
					            	echo("<i class='fa fa-apple fa-5x' aria-hidden='true'></i> &nbsp");
				            	}elseif($user->last_activity_os == 0){
					            	echo("<i class='fa fa-globe fa-5x' aria-hidden='true'></i> &nbsp");
				            	}else{
					            	echo("<i class='fa fa-question fa-5x' aria-hidden='true'></i> &nbsp");
				            	}
			            	}
			            	?>
							</br>
							


		            		<label>Latest pushes (30 days): </label> </br>
		            		<?php if(isset($getOsKind)):
			            		
			            		foreach($getOsKind as $kind) : 
				            	
				            	if($kind['os_kind'] == 2){
				            	echo("<i class='fa fa-android fa-5x' aria-hidden='true'></i> &nbsp");
			            	}elseif($kind['os_kind'] == 1){
				            	echo("<i class='fa fa-apple fa-5x' aria-hidden='true'></i> &nbsp");
			            	}elseif($kind['os_kind'] == 3){
				            	echo("<i class='fa fa-globe fa-5x' aria-hidden='true'></i> &nbsp");
			            	}else{
				            	echo("<i class='fa fa-question fa-5x' aria-hidden='true'></i> &nbsp");
			            	}
				            	endforeach;
				            	
				            	endif;
			            	?>
			            	<hr>
		            		
		            	</div>
		            
		            </div> <!-- User info -->
		            
		            <div class="row">
			            <h3 style="color:#00317c; padding-left: 15px">Addresses</h3>
			            <?php 
				            if(isset($getAddress)):
				            
				            foreach ($getAddress as $address): ?>
			            
			            <div class="col-md-3">
				            
				            <label>Last update: </label><?php echo("  " . $address['last_update'])?> </br>
				            
				            <label>Street: </label><?php echo("  " . $address['address1'])?> </br>
								
							<label>Col: </label><?php echo("  " . $address['address2'])?> </br>
								
							<label>Exterior num:</label><?php echo(" #" . $address['exterior_num'])?> </br>
								
							<label>Interior num:</label><?php echo(" #" . $address['interior_num'])?> </br>
																
							<label>Zip code: </label><?php echo("  " . $address['zip_code'])?> </br>
								
							<label>City: </label><?php echo("  " . $address['city'])?> </br>
								
							<label>State: </label><?php echo("  " . $address['state'])?> <hr>
								
			            </div>
			            <?php endforeach;
				            	endif;
			            ?>
			        </div> <!-- Addresses -->
			            
			        <div class="row">
			            	
			            <h3 style="color:#00317c; padding-left: 15px">Payment cards</h3>
			            <?php if(isset($paymentMethod)):
				            	foreach ($paymentMethod as $pm): ?>
			            
			            <div class="col-md-3">
				            
				            <label>Last update: </label><?php echo("  " . $pm['last_update'])?> </br>
				            
				            <label>Is default: </label><?php echo("  " . $pm['is_default'])?> </br>
				            
				            <label>Card number: </label><?php echo("  " . $pm['card_number'])?> </br>
								
							<label>Name:</label><?php echo("  " . $pm['first_name'] . "  " . $pm['last_name'])?> </br>
								
							<label>Due date:</label><?php echo("  " . $pm['due_date'])?> </br>									
							
							<label>Kind: </label><?php echo("  " . $pm['kind'])?> </br>
							
							<?php if($pm['kind'] == "visa"){echo("<img height='25px' src='".base_url('assets/admin/images/cards/visa.png')."'>");}
			                		elseif($pm['kind'] == "mastercard"){echo("<img height='80px' src='".base_url('assets/admin/images/cards/mastercard.png')."'>");}
			                		elseif($pm['kind'] == "amex"){echo("<img height='80px' src='".base_url('assets/admin/images/cards/amex.png')."'>");}
			                		else{echo("No se ha encontrado el tipo");}?>

							
							<hr>
								
			            </div>
			          
			            <?php endforeach; 
				            	endif;
			            ?>
		            	
		            </div> <!-- Payment cards -->
			        
			        <?php
				        
				        
				         if (isset($getUserEvent)){ ?>
			        </br>       
				    <h3 style="color:#00317c"> User next events </h3>
				        
					    <div class="row">
				        	<div class="col-lg-4">
				        		<label>Event title</label>
				        	</div>
				        	<div class="col-lg-4">
				        		<label>Start date</label>
				        	</div>
				        	<div class="col-lg-4">
				        		<label>End date</label>
				        	</div>
				        </div><hr>
				        
				        <?php foreach($getUserEvent as $event) :
					        $start = strtotime($event['start_date']);

							$startWeekDay = date('l', $start);
							$startDay = date('d', $start);
							$startMonth = date('m', $start);
							$startYear = date('Y', $start);
							
							$end = strtotime($event['end_date']);

							$endWeekDay = date('l', $end);
							$endDay = date('d', $end);
							$endMonth = date('m', $end);
							$endYear = date('Y', $end);
							
				        ?> 
				        
				        <div class="row">
					        <div class="col-lg-4">
						       <?=$event['title'];?>
					        </div>
					        <div class="col-lg-4">
						        <?php echo($startWeekDay.", ". $startMonth ."-". $startDay . ", " . $startYear); ?>
					        </div>
					        <div class="col-lg-4">
						        <?php echo($endWeekDay.", ". $endMonth ."-". $endDay . ", " . $endYear); ?>
					        </div>
				        </div><hr>
				        
				        <?php endforeach; }?>    
			        	
			        
			        
		            <?php if (isset($getUserLocation)){ ?>
		            
				            <h3 style="color:#00317c">Last location: <?php echo($getUserLocation[0]["last_update"]); ?></br> (Last 50 shown) </h3>
				            
				            <div id="map" style="width:50%;height:360px;background:black;color:#00317c"></div>
				            
				            <?php } ?>
				    
				    <div class="dataTable_wrapper">
					    </br>       
				    <h3 style="color:#00317c">User lists (Last 10) &nbsp <a href="<?= base_url('admin/user_lists/index_selected/'.$getFb['id']) ?>" class="btn btn-primary"> See full list </a></h3>
                        <table class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
									<th>List name</th>
                                    <th>Last Update</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($getUserLists)): ?>
                                    <?php foreach ($getUserLists as $key => $list): ?>
                                        <tr class="odd gradeX">
                                            <td><?=$list['list_name']?></td>
                                            <td><?=$list['last_update']?></td>
                                            <td>
	                                            <!---VALIDA SI TIENE UN USUARIO SELECCIONADO -->
	                                            <?php if (count($getUserLists)): ?> 
                                                	<a href="<?= base_url('admin/user_lists/edit_selected/'.$list['id']) ?>" class="btn btn-info">edit</a>   
                                                <?php else: ?>
                                                	<a href="<?= base_url('admin/user_lists/edit/'.$list['id']) ?>" class="btn btn-info">edit</a> 
                                                <?php endif ?>  
                                                 
                                               <a href="<?= base_url('admin/product_lists/index_selected/'.$list['id']) ?>" class="btn btn-primary">products</a>  
                                              <a href="<?= base_url('admin/user_lists/delete/'.$list['id']) ?>" class="btn btn-danger">delete</a>
                                               
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="even gradeC">
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfooter>
                                <tr>
									<th>List name</th>
                                    <th>Last Update</th>
                                    <th>Action</th>
                                </tr>
                            </tfooter>
                        </table>
                    </div>  <!-- users list -->
				   
				    <div class="dataTable_wrapper">
					    </br>       
				    <h3 style="color:#00317c">User Friends (Last 10) &nbsp <a href="<?= base_url('admin/user_friends/index_selected/'.$getFb['id']) ?>" class="btn btn-primary"> See full list </a></h3>
                        <table class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Friend</th>
                                    <th>Last Update</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($getUserFriends)): ?>
                                    <?php foreach ($getUserFriends as $key => $list): ?>
                                        <tr class="odd gradeX">
                                            <td><?=$list['first_name']?>&nbsp<?=$list['last_name']?></td>
                                            <td><?=$list['last_update']?></td>
                                            <td>
	                                            <a href="<?= base_url('admin/sysusers/detail/'.$list['follow_user_id']) ?>" class="btn btn-primary">Go to profile </a>
	                                            <?php if (isset($getUserFriends->first_name)): ?>
                                                	<a href="<?= base_url('admin/user_friends/edit_selected/'.$list['id']) ?>" class="btn btn-info">edit</a>   
                                                <?php else: ?>
                                                	<a href="<?= base_url('admin/user_friends/edit/'.$list['id']) ?>" class="btn btn-info">edit</a>  
                                                <?php endif ?> 
                                                
												<a href="<?= base_url('admin/user_friends/delete/'.$list['id']) ?>" class="btn btn-danger">delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="even gradeC">
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                        
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfooter>
                                <tr>
                                    <th>Friend</th>
                                    <th>Last Update</th>
                                    <th>Action</th>
                                </tr>
                            </tfooter>
                        </table>
                    </div> <!-- User friends -->
				    
				    <div class="dataTable_wrapper">
					    </br>       
				    <h3 style="color:#00317c">User Favorite Products (Last 10) &nbsp <a href="<?= base_url('admin/product_favorites/index_selected/'.$getFb['id']) ?>" class="btn btn-primary"> See full list </a></h3>
                        <table class="table table-striped table-bordered table-hover" >
                            <thead>
                                <tr>
									<th>Image</th>
                                    <th>Product</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($getUserFavoriteProduct)): ?>
                                    <?php foreach ($getUserFavoriteProduct as $product): ?>
                                        <tr class="odd gradeX">
	                                        <td><?php if($product['image_url'] == null){echo("No data");}else{echo('<img src="' . $product['image_url'] . '" height="32px" />');}?></td>
                                            <td><?=$product['product_name']?></td>
                                            <td>
                                                <a href="<?= base_url('admin/product_favorites/edit_selected/'.$product['id']) ?>" class="btn btn-info">edit</a>  
                                                <a href="<?= base_url('admin/product_favorites/delete/'.$product['id']) ?>" class="btn btn-danger">delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="even gradeC">
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfooter>
                                <tr>
									<th>Image</th>
                                    <th>Product</th>
                                    <th>Action</th>
                                </tr>
                            </tfooter>
                        </table>
                    </div> <!-- User favorite products -->
				    
				    <div class="dataTable_wrapper">
					    </br>       
					<h3 style="color:#00317c">User Notifications (Last 10) &nbsp <a href="<?= base_url('admin/notifications/index_selected/'.$getFb['id']) ?>" class="btn btn-primary"> See full list </a></h3>
                        <table class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr> 
	                                <th>Sent by</th>
                                    <th>Message</th>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($getUserNotifications)): ?>
                                    <?php foreach ($getUserNotifications as $notification): ?>
                                        <tr class="odd gradeC">
                                            <td><?php echo($notification['first_name']. " ". $notification['last_name']);?></td>
                                            <td><?php echo($notification['message'])?></td>
                                            <td><img src="<?php echo($notification['image_url'])?>" height="32px" />&nbsp &nbsp<?php echo($notification['product_name'])?></td>
                                            <td>
                                            <?php if($notification['delivery_status'] == 1):
	                                            echo("Sent");
	                                        else:
	                                            echo("Sending");
	                                        endif;
                                            ?>
                                            </td>
                                            <td>
	                                            <?php if ($notification['delivery_status']  != 2): ?>
	                                                <a href="<?= base_url('admin/notifications/delete/'.$notification['id']) ?>" class="btn btn-danger">delete</a>
                                                <?php endif; ?> 
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="even gradeC">

                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                       
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfooter>
                                <tr>
	                                <th>User</th>
                                    <th>Message</th>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </tfooter>
                        </table>
                    </div> <!-- User notifications -->
				    
				    <div class="dataTable_wrapper">
					    </br>       
					<h3 style="color:#00317c">User Chat Conversations (Last 10) &nbsp <a href="<?= base_url('admin/sysusers/chats/'.$getFb['id'])?>" class="btn btn-primary"> See full list </a></h3>
                        <table class="table table-striped table-bordered table-hover" id="dataTables-ordering-desc">
                            <thead>
                                <tr>
	                                <th>Id</th>
                                    <th>Message</th>
                                    <th>Last Update</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($getUserChats)): ?>
                                    <?php foreach ($getUserChats as $chats): ?>
                                        <tr class="odd gradeX">
	                                        <td><?=$chats['chat_id']?></td>
                                            <td><?=$chats['message']?></td>
                                            <td><?=$chats['last_update']?></td>
                                            <td>
                                                <a href="<?= base_url('admin/chat_messages/index_selected/'.$chats['chat_id'])?>" class="btn btn-primary">see messages</a> 
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="even gradeC">
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                        
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfooter>
                                <tr>
	                                <th>Id</th>
                                    <th>Message</th>
                                    <th>Last Update</th>
                                    <th>Action</th>
                                </tr>

                            </tfooter>
                        </table>
                    </div> <!-- User conversations -->
				   
	            <?php endif; ?> <!-- validation -->
	            </div>
			</div>
		</div>
		
	</div>
	
	
</div>

 <?php if (isset($getUserLocation)) : $i = 0; ?>

<script>
		
	var locations = [
	
		<?php foreach($getUserLocation as $location): ?>
	
      [ <?=$location['latitude']?>, <?=$location['longitude']?>],

      <?php endforeach; ?>
      
      [ null, null]
    ];	
		
		console.log(locations);
		
	function myMap() {
		
		
	var mapOptions = {
	    center: new google.maps.LatLng(	<?php echo($getUserLocation[0]['latitude']); ?> , <?php echo($getUserLocation[0]['longitude']); ?> ),
	    zoom: 17,
	    mapTypeId: google.maps.MapTypeId.HYBRID
	}
	var map = new google.maps.Map(document.getElementById("map"), mapOptions);
	
	for (i = 0; i < locations.length-1; i++) {  
      marker = new google.maps.Marker({
        position: new google.maps.LatLng(locations[i][0], locations[i][1]),
        map: map
      });
    }

	}
	
	</script>

	
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFmo3rxy-wnc4th_fLWRNJoe7Dc1v9zNk&callback=myMap"></script>
	
<?php endif; ?>
