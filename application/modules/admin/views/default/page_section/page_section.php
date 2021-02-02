<div id="page-wrapper">
	<div class="row">
		<div class="col-lg-12">
			<h2>
				Page Section
				<a  href="<?= base_url('admin/page_sections') ?>" class="btn btn-warning">Go back to page_sections listing</a>
			</h2>
		</div>
		<!-- /.col-lg-12 -->
	</div>
	<!-- /.row -->
	<div class="row">
		<div class="col-lg-12">
			<div class="panel panel-default">
				<div class="panel-heading">
				  <?php if (empty($page_section->id)): ?>
					Create Page Section
				  <?php else: ?>
					Update Page Section
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
							
							<form role="form" method="POST" action="<?=base_url('admin/page_sections/edit/'.$page_section->id)?>">
							<!-- Lista de páginas existentes -->
								<div class="form-group">
									<label>WebPage</label>
									<select class="form-control" name="webpage_id" id="webpage_id">
										<?php foreach ($webpages as $webpage): ?>
											<option value="<?=$webpage['id']?>" <?= ($page_section->webpage_id == $webpage['id']) ? 'selected' : '';?> ><?=$webpage['title']?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="form-group">
									<label>Kind</label>
									<select class="form-control" name="kind" id="kind">
										<?php foreach ($kinds as $key => $list): ?>
											<option value="<?=$key?>" <?= ($page_section->kind == $key) ? 'selected' : '';?> ><?=$list?></option>
										
										<?php endforeach; ?>
									</select>
								</div>
								<div class="form-group">
									<label>Order</label>
									<input class="form-control" placeholder="Enter order" id="order" name="order"  value="<?=$page_section->order?>">
								</div>
								<div class="form-group">
									<label>Content</label>
									<textarea class="form-control" placeholder="Enter content" id="content" name="content"><?=$page_section->content?></textarea>
								</div>
								

								<a  href="<?= base_url('admin/page_sections/delete/'.$page_section->id) ?>" class="btn btn-danger">Delete</a>
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
<!-- /#page-wrapper -->

<script>
	
	var callback = function(){
	  // Handler when the DOM is fully loaded
	  $('#content').summernote();
	};
	
	if (
	    document.readyState === "complete" ||
	    (document.readyState !== "loading" && !document.documentElement.doScroll)
	) {
	  callback();
	} else {
	  document.addEventListener("DOMContentLoaded", callback);
	}
</script>
