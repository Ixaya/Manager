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
											<td><?=$list['kind']?></td>
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
