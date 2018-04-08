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
                        <div class="col-lg-6">
                            <form role="form" method="POST" action="<?=base_url('admin/sysusers/edit/'.$user->id)?>">
                                <!--
                                <div class="form-group">
									<label>Client</label>
									<select class="form-control" id="group_id" name="group_id">
                                        <?php foreach ($clients as $client): ?>
                                        <option value="<?=$client->id?>" <?=ui_selected_item($user_group->name,$group->name)?></optio><?=$group->name?></option>
										<?php endforeach; ?>
                                    </select>
                                </div>
-->								<div class="form-group">
                                    <label>ID:</label>
                                    <text>&nbsp<?=$user->id?></text>
                                </div>
                                <div class="form-group">
                                    <label>Last update: </label>
                                    <text>&nbsp<?=$user->last_update?><text>
                                </div>
								<div class="form-group">
                                    <label>IP address</label>
                                    <input class="form-control" value="<?=$user->ip_address?>" placeholder="Enter IP address" id="ip_address" name="ip_address">
                                </div>
                                <div class="form-group">
                                    <label>Username</label>
                                    <input class="form-control" value="<?=$user->username?>" placeholder="Enter username" id="username" name="username">
                                </div>
                                <div class="form-group">
                                    <label>Password</label>
                                    <input class="form-control" value="" placeholder="Only enter password if you want to change it" id="password" name="password">
                                </div>
                                
                                <div class="form-group">
                                    <label>Email</label>
                                    <input class="form-control" value="<?=$user->email?>" placeholder="Enter group description" id="email" name="email">
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
                                    <input class="form-control" value="<?=$user->company?>" placeholder="Enter company" id="company" name="company">
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input class="form-control" value="<?=$user->phone?>" placeholder="Enter phone" id="phone" name="phone">
                                </div>
								<div class="form-group">
                                    <label>Facebook ID</label>
                                    <input class="form-control" value="<?=$user->facebook_id?>" placeholder="Enter Facebook ID" id="facebook_id" name="facebook_id">
                                </div>
								<div class="form-group">
                                    <label>Activation code</label>
                                    <input class="form-control" value="<?=$user->activation_code?>" placeholder="Enter activation code" id="activation_code" name="activation_code">
                                </div>
                                <div class="form-group">
                                    <label>Created date</label>
                                    <input class="form-control" value="<?=$user->created_on?>" placeholder="Enter created date" id="created_on" name="created_on">
                                </div>
                                <div class="form-group">
                                    <label>Last login</label>
                                    <input class="form-control" value="<?=$user->last_login?>" placeholder="Enter last login" id="last_login" name="last_login">
                                </div>
                                <div class="form-group">
                                    <label>Facebook token</label>
                                    <input class="form-control" value="<?=$user->fb_token?>" placeholder="Enter facebook token" id="fb_token" name="fb_token">
                                </div>
                                <div class="form-group">
                                    <label>Facebook login</label>
                                    <input class="form-control" value="<?=$user->fb_login?>" placeholder="Enter facebook login" id="fb_login" name="fb_login">
                                </div>
                                <div class="form-group">
                                    <label>Facebook last sync</label>
                                    <input class="form-control" value="<?=$user->fb_last_sync?>" placeholder="Enter facebook last sync" id="fb_last_sync" name="fb_last_sync">
                                </div>
                                <div class="form-group">
                                    <label>Facebook response</label>
                                    <input class="form-control" value="<?=$user->fb_response?>" placeholder="Enter facebook response" id="fb_response" name="fb_response">
                                </div>
                                <div class="form-group">
                                    <label>Active</label>
<!--                                     <input class="form-control" value="<?=$user->active?>" placeholder="User is active" id="active" name="active"> -->
<!--  									<?=form_dropdown('active', array(1=>'True',0=>'False'), ($user->active == 't') ? '1' : '0', 'class="form-control" id="active"')?> -->
									<?=form_dropdown('active', array(1=>'True',0=>'False'), $user->active, 'class="form-control" id="active"')?>
                                </div>
                                <div class="form-group">
                                    <label>Is public</label>
									<?=form_dropdown('is_public', array(1=>'True',0=>'False'), $user->is_public, 'class="form-control" id="is_public"')?>
                                </div>
                                <button type="submit" class="btn btn-primary">Update</button>
                           
							 </form>
                            <br /><br />
                            <?php echo form_open_multipart(base_url('admin/sysusers/do_upload/'.$user->id));?>	
                                <div class="form-group">
                                    <label>Current Image</label>
                                    <?php if(count($user->image_name) > 0) : ?>
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
