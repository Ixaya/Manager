



<div id="page-wrapper">
	
	
	<div class="row">
		<div class="col-lg-12">
			<div class="page-header users-header">
				<h2>
					Page Items
					<a  href="<?= base_url('admin/page_items/create') ?>" class="btn btn-success">Add a new</a>
				</h2>
			</div>
		</div>
		<!-- /.col-lg-12 -->
	</div>
	
	
	<div class="row">
		 <?php if ($this->session->flashdata('message')): ?>
		<div class="col-lg-12 col-md-12">
			<div class="alert alert-info alert-dismissable">
				<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
				<?=$this->session->flashdata('message')?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Total Page Items -->
		<div class="col-lg-2 col-md-4">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-list-ol fa-3x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><?= $page_items_count ?></div>
							<div>Total PageItems</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- Total Page Items -->
		<div class="col-lg-2 col-md-4">
			<div class="panel panel-warning">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-square fa-3x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><?= $icon_items?></div>
							<div>Icon Items</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Showcases Items -->
		<div class="col-lg-2 col-md-4">
			<div class="panel panel-danger">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-eye fa-3x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><?= $showcases ?></div>
							<div>Total Showcases</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Testimonials -->
		<div class="col-lg-2 col-md-4">
			<div class="panel panel-success">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-comments fa-3x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><?= $testimonials ?></div>
							<div>Total Testimonials</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Social Networks -->
		<div class="col-lg-2 col-md-4">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<span>
							<i class="fa fa-facebook fa-2x"></i>
							<i class="fa fa-instagram fa-1x"></i>
							<i class="fa fa-twitter fa-2x"></i>
							</span>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><?= $social_networks ?></div>
							<div>Social Networks</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- About Items -->
		<div class="col-lg-2 col-md-4">
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-info fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><?= $about_items ?></div>
							<div>About Items</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		
	</div>
	<!-- /.row -->
	<div class="row">
		<div class="col-lg-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					Page Items listing
				</div>
				<!-- /.panel-heading -->
				<div class="panel-body">
					<div class="dataTable_wrapper">
						<table class="table table-striped table-bordered table-hover" id="dataTables-main">
							<thead>
								<tr>
									<th>Title</th>
									<th>Description</th>
									<th>Kind</th>
									<th>Last Update</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody>
								<?php if (count($page_items)): ?>
									<?php foreach ($page_items as $key => $list): ?>
										<tr class="odd gradeX">
											<td><?=$list['title']?></td>
											<td><?=$list['description']?></td>
											<td><?=$kinds[$list['kind']]?></td>
											<td><?=$list['last_update']?></td>
											<td>
												<a href="<?= base_url('admin/page_items/edit/'.$list['id']) ?>" class="btn btn-info">edit</a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else: ?>
									<tr class="even gradeC">
										<td>No data</td>
										<td>No data</td>
										<td>No data</td>
										<td>No data</td>
										<td>
											<a  href="<?= base_url('admin/page_items/create') ?>" class="btn btn-success">Add a new</a>
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
							<tfooter>
								<tr>
									<th>Title</th>
									<th>Description</th>
									<th>Kind</th>
									<th>Last Update</th>
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
</div>
<!-- /#page-wrapper -->
