<div id="page-wrapper">
	<div class="row">
		<div class="col-lg-12">
			<h2>
				Examples
				<a  href="<?= base_url('admin/examples') ?>" class="btn btn-warning">Go back to examples listing</a>
			</h2>
		</div>
		<!-- /.col-lg-12 -->
	</div>
	<!-- /.row -->
	<div class="row">
		<div class="col-lg-12">
			<div class="panel panel-default">
				<div class="panel-heading">
				  <?php if (empty($example->id)): ?>
					Create example
				  <?php else: ?>
					Update example
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
							<form role="form" method="POST" action="<?=base_url('admin/examples/edit/'.$example->id)?>">
								<div class="form-group">
									<label>Title</label>
									<input class="form-control" placeholder="Enter example title" id="title"  value="<?=$example->title?>" name="title">
								</div>
								<div class="form-group">
									<label>Example</label>
									<input class="form-control" placeholder="Enter example" id="example" name="example"  value="<?=$example->example?>">
								</div>
								<button type="submit" class="btn btn-primary">Save</button>
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
<!-- /#page-wrapper -->
