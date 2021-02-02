  <!-- Footer -->
  <footer class="footer bg-light">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 h-100 text-center text-lg-left my-auto">
          <ul class="list-inline mb-2">
		  <?php foreach ($this->_footer_links as $item): ?>

            <li class="list-inline-item">
              <a href="<?= base_url($item['url']) ?>"><?= $item['title'] ?></a>
            </li>
            <li class="list-inline-item">&sdot;</li>
           <?php endforeach; ?>
          </ul>
          <p class="text-muted small mb-4 mb-lg-0">&copy; Your Website 2019. All Rights Reserved.</p>
        </div>
        <div class="col-lg-6 h-100 text-center text-lg-right my-auto">
          <ul class="list-inline mb-0">
	          
	        <?php foreach ($this->_social_networks as $item): ?>
            <li class="list-inline-item mr-3">
              <a href="<?= $item['url'] ?>">
                <i class="fab <?= $item['faicon']?> fa-2x fa-fw"></i>
              </a>
            </li>
            <?php endforeach; ?>

          </ul>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bootstrap core JavaScript -->
  <script src="<?=base_url()?>assets/frontend/default/vendor/jquery/jquery.min.js"></script>
  <script src="<?=base_url()?>assets/frontend/default/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>
