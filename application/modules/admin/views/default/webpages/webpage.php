<div id="webpage-wrapper">
	<div class="row">
		<div class="col-lg-12">
			<h2>
				Webpage Items
				<a  href="<?= base_url('admin/webpages') ?>" class="btn btn-warning">Go back to webpages listing</a>
			</h2>
		</div>
		<!-- /.col-lg-12 -->
	</div>
	<!-- /.row -->
	<div class="row">
		<div class="col-lg-12">
			<div class="panel panel-default">
				<div class="panel-heading">
				  <?php if (empty($webpage->id)): ?>
					Create webpage Item
				  <?php else: ?>
					Update webpage Item
				  <?php endif; ?>
				</div>
				<div class="panel-body">
					<div class="row">
						<?php if ($this->session->flashdata('message')): ?>
						<div class="col-lg-12 col-md-12">
							<div class="alert alert-info alert-dismissable">
								<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
								<?=$this->session->flashdata('message')?>
							</div>
						</div>
						<?php endif; ?>
						<div class="col-lg-6">
							<form role="form" method="POST" action="<?=base_url('admin/webpages/edit/'.$webpage->id)?>">
								<div class="form-group">
									<label>Title</label>
									<input class="form-control" placeholder="Enter webpage title" id="title"  value="<?=$webpage->title?>" name="title">
								</div>
								<div class="form-group">
									<label>Slug</label>
									<input class="form-control" placeholder="Enter description" id="slug" name="slug"  value="<?=$webpage->slug?>">
								</div>
								<div class="form-group">
									<label>Kind</label>
									<select class="form-control" name="kind" id="kind">
										<?php foreach ($kinds as $key => $list): ?>
											<option value="<?=$key?>" <?= ($webpage->kind == $key) ? 'selected' : '';?> ><?=$list?></option>
										
										<?php endforeach; ?>
									</select>
								</div>
								<a  href="<?= base_url('admin/webpages/delete/'.$webpage->id) ?>" class="btn btn-danger">Delete</a>
								<button type="submit" class="btn btn-primary pull-right">Save</button>
								
							</form>
						</div>
					</div>
					<!-- /.row (nested) -->
				</div>
				<!-- /.panel-body -->
			</div>
			<!-- /.panel -->
		</div>
		<!-- /.col-lg-12 -->
	</div>
	<!-- /.row -->
</div>
<!-- /#webpage-wrapper -->
