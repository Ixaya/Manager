<style>
.dataTables_processing{
	z-index: 10;
	height: 118% !important;
	top: -25px !important;
	padding-top: 9% !important;
	font-size: 50px !important;
	background-color:rgba(255, 255, 255, 0.1) !important;
	color:#337ab7;
	}
</style>

<div id="page-wrapper">
	<div class="row">
		<div class="col-lg-12">
			<div class="page-header users-header">
				<h2>
					Users
					<a  href="<?= base_url('admin/sysusers/create') ?>" class="btn btn-success">Add New</a>
				</h2>
				
			</div>
		</div>
		<!-- /.col-lg-12 -->
	</div>
	<!-- /.row -->
	<div class="row">
		<div class="col-lg-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					
				</div>
				<!-- /.panel-heading -->
				<div class="panel-body">
					
					
					<div class="dataTable_wrapper">
						<table class="table table-striped table-bordered table-hover" id="dataTables-main">
							<thead>
								<tr>
									<th>User ID</th>
									<th>Fullname</th>
									<th>Email</th>
									<th>IP Address</th>
									<th>Created On</th>
									<th>Last Activity</th>
									<th>Action</th>
								</tr>
							</thead>

							<tbody>
								<?php date_default_timezone_set('America/Mexico_City'); ?>
								<?php if (count($users)): ?>
									<?php foreach ($users as $user): ?>
										<tr class="odd gradeX">
											<td><?=$user['id']?></td>

											<td><?=$user['first_name'] .' '.$user['last_name'] ?></td>
											<td><?=$user['email']?></td>
											<td><?=$user['ip_address']?></td>
											<td><?= $user['created_on_date']?></td>
											<td><?=$user['last_activity_date']?></td>
											<td>
												<a href="<?= base_url('admin/sysusers/edit/'.$user['id']) ?>" class="btn btn-primary">Edit</a>
												<a href="<?= base_url('admin/sysusers/delete/'.$user['id']) ?>" class="btn btn-danger">Delete</a>
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
									<th>User ID</th>
									<th>Fullname</th>
									<th>Email</th>
									<th>IP Address</th>
									<th>Created On</th>
									<th>Last Activity</th>
									<th>Action</th>
								</tr>
								
							</tfooter>
						</table>
					</div>
				</div>
				<!-- /.panel-body -->
			</div>
			<!-- /.panel -->
		</div>
		<!-- /.col-lg-12 -->
	</div>
</div>
<!-- /#page-wrapper -->




<script>
	$(document).ready(function() {
		$('#dataTables-ajax').DataTable({
			  "bServerSide": true,
			  "lengthMenu": [[10, 25, 50, 500, -1], [10, 25, 50, 500, "All"]],
			  "sAjaxSource": "<?=base_url().'admin/sysusers/index_ajax' ?>",
			  
			columns: [
				{ "data": "id" },
				{ "data": "first_name" },
				{ "data": "email" },
				{ "data": "ip_address" },
				{ "data": "created_on" },
				{ "data": "last_activity_date" },
				{ "data": null, "defaultContent":"<a href='detail' class='btn btn-primary'>details</a>" }
			]
		});
	});
</script>


