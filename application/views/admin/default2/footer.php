        </div>
        <!-- /.container-fluid -->

      </div>
      <!-- End of Main Content -->

      <!-- Footer -->
      <footer class="sticky-footer bg-white">
        <div class="container my-auto">
          <div class="copyright text-center my-auto">
            <span>Copyright &copy; Your Website 2019</span>
          </div>
        </div>
      </footer>
      <!-- End of Footer -->

    </div>
    <!-- End of Content Wrapper -->
  </div>
  <!-- End of Page Wrapper -->

 <!-- Scroll to Top Button-->
  <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
  </a>

  <!-- Logout Modal-->
  <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
          <button class="close" type="button" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
          <a class="btn btn-primary" href="login.html">Logout</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap core JavaScript-->
  <script src="<?=base_url('assets/admin/default2/')?>vendor/jquery/jquery.min.js"></script>
  <script src="<?=base_url('assets/admin/default2/')?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript-->
  <script src="<?=base_url('assets/admin/default2/')?>vendor/jquery-easing/jquery.easing.min.js"></script>

  <!-- Custom scripts for all pages-->
  <script src="<?=base_url('assets/admin/default2/')?>js/sb-admin-2.min.js"></script>

  <!-- Page level plugins -->
  <script src="<?=base_url('assets/admin/default2/')?>vendor/chart.js/Chart.min.js"></script>

  <!-- Page level custom scripts -->
  <script src="<?=base_url('assets/admin/default2/')?>js/demo/chart-area-demo.js"></script>
  <script src="<?=base_url('assets/admin/default2/')?>js/demo/chart-pie-demo.js"></script>

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
