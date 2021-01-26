<div id="page-wrapper">
	<div class="row">
		<div class="col-lg-12">
			<h1 class="page-header"><i class="fa fa-dashboard fa-fw"></i> Dashboard</h1>
		</div>
		<!-- /.col-lg-12 -->
	</div>
	<!-- /.row -->
	<div class="row">
		 <?php if ($this->session->flashdata('message')): ?>
		<div class="col-lg-12 col-md-12">
			<div class="alert alert-info alert-dismissable">
				<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
				<?=$this->session->flashdata('message')?>
			</div>
		</div>
		<?php endif; ?>

<!-- Example -->
		<div class="col-lg-3 col-md-6">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-square fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><?= $page_items_count ?></div>
							<div>Page Items</div>
						</div>
					</div>
				</div>
				<a href="#">
					<div class="panel-footer">
						<span class="pull-left"><a href="<?= base_url('admin/page_items') ?>">Page Items</a></span>
						<span class="pull-right"><a href="<?= base_url('admin/page_items/') ?>"><i class="fa fa-arrow-circle-right"></i></a></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
	</div>



</div>
<!-- https://fontawesome.com/v4.7.0/icons/ -->
<style>

	.panel-purple{
	  color: #ffffff;
	  background-color: #6a47aa;
	  border-color: #6a47aa;
	}

</style>
<!-- /#page-wrapper -->
