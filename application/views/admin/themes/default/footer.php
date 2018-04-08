</div>
<!-- /#wrapper -->

<!-- jQuery -->
<script src="<?= base_url() ?>assets/admin/js/jquery.min.js"></script>

<!-- Bootstrap Core JavaScript -->
<script src="<?= base_url() ?>assets/admin/js/bootstrap.min.js"></script>

<!-- Metis Menu Plugin JavaScript -->
<script src="<?= base_url() ?>assets/admin/js/metisMenu.min.js"></script>

<!-- DataTables JavaScript -->
<script src="<?= base_url() ?>assets/admin/js/jquery.dataTables.min.js"></script>
<!-- <script src="<?= base_url() ?>assets/admin/js/dataTables.treeGrid.js"></script> -->
<script src="<?= base_url() ?>assets/admin/js/jquery.treetable.js"></script>
<script src="<?= base_url() ?>assets/admin/js/dataTables.treeTable.js"></script>
<script src="<?= base_url() ?>assets/admin/js/dataTables.bootstrap.min.js"></script>

<!-- Custom Theme JavaScript -->
<script src="<?= base_url() ?>assets/admin/js/sb-admin-2.js"></script>


<script src="<?= base_url() ?>assets/admin/js/moment.min.js"></script>
<script src="<?= base_url() ?>assets/admin/js/bootstrap-datetimepicker.min.js"></script>

<script>
    $(document).ready(function() {
        $('#dataTables-main').DataTable({
	        pageLength: 10,
			lengthMenu: [[10, 20, 25, 50, 100, -1], [10, 20, 25, 50, 100, 'All']],
            responsive: true
        });

         $('#dataTables-detail').DataTable({
            responsive: true
        });
    });
</script>
<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {
       if(document.getElementById('dataTables-tree')){
			var table = $('#dataTables-tree').DataTable({
				pageLength: 5,
				lengthMenu: [3, 5, 10, 25, 50],
				ordering: false,
				treetable: {
					expandable: true
				}
			} );
		}
		
		if(document.getElementById('dataTables-server')){
			<?php $ajax_url = ""; if(isset($data_table_url)){ $ajax_url= $data_table_url; } ?>
			var p = $( "#dataTables-server" );
			var table = $('#dataTables-server').DataTable({
					"processing": true,
					"serverSide": true,
					"ajax": {
						"url": "<?= base_url().$ajax_url ?>",
						"type":"POST",
						"dataSrc": function ( json ) {
                //Make your callback here.
                $('body,html').animate({
						scrollTop: 0
        		}, 400);
                //alert("Done!");
                return json.data;
            }
        			}
			} );
		}

	} );
</script>

<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {

		var table = $('#dataTables-tree').DataTable({
			pageLength: 5,
			lengthMenu: [3, 5, 10, 25, 50],
			ordering: false,
			treetable: {
				expandable: true
			}
		} );

	} );
</script>
<script type="text/javascript" charset="utf-8">
	if (typeof needsCalendar !== 'undefined')
		{
		    $(document).ready(function() {
		        $('#datetimepicker').datetimepicker({
		                sideBySide: true,
				    allowInputToggle: true,
				    icons: {
				        time: "fa fa-clock-o",
				        date: "fa fa-calendar",
				        up: "fa fa-arrow-up",
				        down: "fa fa-arrow-down",
				    },
				});
			});
		}
</script>
</body>

</html>
