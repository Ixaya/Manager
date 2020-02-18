</div>
<!-- /#wrapper -->

<!-- jQuery -->
<script src="<?= base_url() ?>assets/admin/default/js/jquery.min.js"></script>

<!-- Bootstrap Core JavaScript -->
<script src="<?= base_url() ?>assets/admin/default/js/bootstrap.min.js"></script>

<!-- Metis Menu Plugin JavaScript -->
<script src="<?= base_url() ?>assets/admin/default/js/metisMenu.min.js"></script>

<!-- DataTables JavaScript -->
<script src="<?= base_url() ?>assets/admin/default/js/jquery.dataTables.min.js"></script>
<!-- <script src="<?= base_url() ?>assets/admin/default/js/dataTables.treeGrid.js"></script> -->
<script src="<?= base_url() ?>assets/admin/default/js/jquery.treetable.js"></script>
<script src="<?= base_url() ?>assets/admin/default/js/dataTables.treeTable.js"></script>
<script src="<?= base_url() ?>assets/admin/default/js/dataTables.bootstrap.min.js"></script>

<!-- Custom Theme JavaScript -->
<script src="<?= base_url() ?>assets/admin/default/js/sb-admin-2.js"></script>


<script src="<?= base_url() ?>assets/admin/default/js/moment.min.js"></script>
<script src="<?= base_url() ?>assets/admin/default/js/bootstrap-datetimepicker.min.js"></script>

<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {
		if($('#dataTables-main')){
			$('#dataTables-main').DataTable({
				pageLength: 10,
				lengthMenu: [[10, 20, 25, 50, 100, -1], [10, 20, 25, 50, 100, 'All']],
				responsive: true
			});
		}

		if($('#dataTables-detail')){
			 $('#dataTables-detail').DataTable({
				responsive: true
			});
		}

		if($('#dataTables-tree')){
			var table = $('#dataTables-tree').DataTable({
				pageLength: 5,
				lengthMenu: [3, 5, 10, 25, 50],
				ordering: false,
				treetable: {
					expandable: true
				}
			});
		}
	});
</script>

<?php if (isset($data_table_url)): ?>
<script type="text/javascript" charset="utf-8">
	$(document).ready(function()
	{
		if($("#dataTables-server"))
		{
			$('#dataTables-server').DataTable({
				lengthMenu: [10, 25, 50, 100, 200],
				processing: true,
				serverSide: true,
				autoWidth: false,
				responsive: true,
				ajax: {
					"url": "<?= base_url($data_table_url) ?>",
					"type":"POST",
					"dataSrc": function (json) {
						$(window).scrollTop($('#dataTables-server').offset().top);

						return json.data;
					}
				},
				columnDefs: [
					{ targets: 'no-sort', orderable: false }
				]
			});
		}
	} );
</script>
<?php endif; ?>
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
