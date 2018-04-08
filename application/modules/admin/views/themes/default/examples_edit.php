<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h2>
                Examples
                <a  href="<?= base_url('admin/addresses') ?>" class="btn btn-warning">Go back to examples listing</a>
            </h2>
        </div>
        <!-- /.col-lg-12 -->
    </div>
    <!-- /.row -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Update example
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
                            <form role="form" method="POST" action="<?=base_url('admin/addresses/edit/'.$addresses->id)?>">
	                            <div class="form-group">
                                    <label>User</label>
                                    <select class="form-control" id="user_id" name="user_id">
                                        <?php foreach ($users as $user => $list):?>
			                                    <option value="<?=$list['id']?>"<?=ui_selected_item($list['id'],$user_list->user_id)?>><?=$list['first_name']?>&nbsp<?=$list['last_name']?></option>
										<?php endforeach;?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>List name</label>
                                    <input class="form-control" value="<?=$user_list->list_name?>" id="list_name" name="list_name">
                                </div>                                
                                <div class="form-group">
                                    <label>Is public</label>
                                    <?php if ($user_list->is_public == 0 ): ?>
	                                    	<input type="checkbox" id="is_public" name="is_public" value="0">
									<?php else:?>		
											<input type="checkbox" checked="checked" id="is_public" name="is_public" value="1">
	                                <?php endif ?>
                                </div>
                                <button type="submit" class="btn btn-primary">Update</button>
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
