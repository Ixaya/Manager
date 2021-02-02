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
					Update user
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
							<form role="form" method="POST" action="<?=base_url('admin/sysusers/edit/'.$user->id)?>">
								<div class="form-group">
									<label>ID:</label>
									<text>&nbsp<?=$user->id?></text>
								</div>
								<div class="form-group">
									<label>Last update: </label>
									<text>&nbsp<?=$user->last_update?><text>
								</div>
								<div class="form-group">
									<label>IP address: </label>
									<text>&nbsp<?=$user->ip_address?><text>
								</div>
								<div class="form-group">
									<label>Username</label>
									<input class="form-control" value="<?=$user->username?>" placeholder="Enter username" id="username" name="username">
								</div>
								<div class="form-group">
									<label>Email</label>
									<input class="form-control" value="<?=$user->email?>" placeholder="Enter group description" id="email" name="email">
								</div>
								<div class="form-group">
									<label>Password</label>
									<input class="form-control" value="" placeholder="Only enter password if you want to change it" id="password" name="password">
								</div>							  
								
								<div class="form-group">
									<label>User Group</label>
									<select class="form-control" id="group_id" name="group_id">
										<?php foreach ($groups as $group): ?>
										<option value="<?=$group->id?>" <?=ui_selected_item($user_group->name,$group->name)?></option><?=$group->name?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="form-group">
									<label>First Name</label>
									<input class="form-control" value="<?=$user->first_name?>" placeholder="Enter first name" id="first_name" name="first_name">
								</div>
								<div class="form-group">
									<label>Last Name</label>
									<input class="form-control" value="<?=$user->last_name?>" placeholder="Enter last name" id="last_name" name="last_name">
								</div>
								<div class="form-group">
									<label>Company</label>
									<input class="form-control" value="<?=$user->company?>" placeholder="Enter Company" id="company" name="company">
								</div>
								<br />
								<div class="form-group">
									<label>Active</label>
									
<!--									 <input class="form-control" value="<?=$user->active?>" placeholder="User is active" id="active" name="active"> -->
<!--  									<?=form_dropdown('active', array(1=>'True',0=>'False'), ($user->active == 't') ? '1' : '0', 'class="form-control" id="active"')?> -->
									<?=form_dropdown('active', array(1=>'True',0=>'False'), $user->active, 'class="form-control" id="active"')?>
								</div>
								<button type="submit" class="btn btn-primary">Update</button>
						   
							 </form>
							<br /><br />
							<?php echo form_open_multipart(base_url('admin/sysusers/do_upload/'.$user->id));?>	
								<div class="form-group">
									<label>Current Image</label>
									<?php if(!empty($user->image_name)) : ?>
										<div> <img height="200px" src="<?php echo($user->image_url); ?>" /> </div>
									<?php endif; ?>
								</div>
								<div class="form-group">
									<label>Upload New Image</label>								
									<input type="file" name="userfile" size="20" />
								</div>
								<button type="submit" class="btn btn-primary">Update Image</button>
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
