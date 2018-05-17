<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <div class="page-header users-header">
	            <h2> General users info
                     <a href="<?= base_url('admin/sysusers') ?>" class="btn btn-primary"> Go back to sysusers </a>
	            </h2>
	            
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    
                </div>
                
                <div class="panel-body">
	                
	            <h4 style="color:#00317c"> Estimated IOS Installations: <?php echo($getIosInstallations[0]['COUNT(DISTINCT user_id)'])?></h3>
                <h4 style="color:#00317c"> Estimated Android Installations: <?php echo($getAndroidInstallations[0]['COUNT(DISTINCT user_id)'])?></h3>
                <h4 style="color:#00317c"> Estimated male users: <?php echo($getCountUserMale[0]['COUNT(id)'])?></h3>
                <h4 style="color:#00317c"> Estimated female users: <?php echo($getCountUserFemale[0]['COUNT(id)'])?></h3>
                        
                        </br>
                        <h3 style="color:#00317c"> Top 10 Products</h3>
                
                <table class="table table-striped table-bordered table-hover" id="dataTables-ordering-desc">
                            <thead>
                                <tr>
	                                <th>Product id</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Times bought</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($getTopMatchedProducts)): ?>
                                    <?php foreach ($getTopMatchedProducts as $topShared): ?>
                                        <tr class="odd gradeX">
	                                        <td><?=$topShared['product_id']?></td>
                                            <td> <image height="40px" src="<?=$topShared['image_url']?>"></td>
                                            <td><?=$topShared['product_name']?></td>
                                            <td><?=$topShared['count(*)']?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="even gradeC">
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                        <td>No data</td>
                                        
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfooter>
                                <tr>
	                                <th>Product id</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Times bought</th>
                                </tr>

                            </tfooter>
                        </table>
                
                		
                
                
	                
                </div>
                
            </div>
        </div>
    </div>
</div>