<div id="page-wrapper">
	<div class="row">
		<div class="col-lg-12">
			<h2>
				Users
				<a  href="<?= base_url('admin/sysusers') ?>" class="btn btn-warning">Go back to users listing</a>
			</h2>
		</div>
		<!-- /.col-lg-12 -->
	</div>
	<!-- /.row -->
	<div class="row">
		<div class="col-lg-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					Create new user
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
							<form role="form" method="POST" action="<?=base_url('admin/sysusers/create')?>">
								<div class="form-group">
									<label>Username</label>
									<input class="form-control"  placeholder="Enter username" id="username" name="username"  required>
								</div>
								<div class="form-group">
									<label>Email</label>
									<input class="form-control" placeholder="Enter email" id="email" name="email"  required>
								</div>
								<div class="form-group">
									<label>Password</label>
									<input type="password" class="form-control" placeholder="Enter password" id="password" name="password"  required>
								</div>
								<div class="form-group">
									<label>User Group</label>
									<select class="form-control" id="group_id" name="group_id">
										<?php foreach ($groups as $group): ?>
										<option value="<?=$group->id?>"><?=$group->name?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="form-group">
									<label>First Name</label>
									<input class="form-control" placeholder="Enter first name" id="first_name" name="first_name"  required>
								</div>
								<div class="form-group">
									<label>Last Name</label>
									<input class="form-control" placeholder="Enter last name" id="last_name" name="last_name"  required>
								</div>

								<div class="form-group">
									<label>Company</label>
									<input class="form-control" placeholder="Enter Company" id="company" name="company">
								</div>
								<br />
								<div class="form-group">
									<label>Activate</label>
									<?=form_dropdown('active', array(1=>'True',0=>'False'), 1, 'class="form-control" id="active"')?>
								</div>
								<br/>
								<button type="submit" class="btn btn-primary">Save</button>
								<button type="reset" class="btn btn-default">Reset</button>
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
