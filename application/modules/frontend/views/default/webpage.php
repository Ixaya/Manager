<!--
  <header class="masthead text-white text-center">
    <div class="overlay"></div>
    <div class="container">
      <div class="row">
        <div class="col-xl-9 mx-auto">
          <h1 class="mb-5">Build a landing page for your business or project and generate more leads!</h1>
        </div>
        <div class="col-md-10 col-lg-8 col-xl-7 mx-auto">
          <form>
            <div class="form-row">
              <div class="col-12 col-md-9 mb-2 mb-md-0">
                <input type="email" class="form-control form-control-lg" placeholder="Enter your email...">
              </div>
              <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-block btn-lg btn-primary">Sign up!</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </header>
-->
  <!-- Message -->
 <section class="bg-light text-center">
    <div class="container">
      	<?php if ($this->session->flashdata('message')): ?>
		<div class="row">
			<div class="col-md-6 offset-md-3">
				
				<div class="alert alert-<?= $this->session->flashdata('message-kind') ?> alert-dismissible fade show" role="alert">
					<a href="#" class="close" data-dismiss="alert">&times;</a>
					<?= $this->session->flashdata('message') ?>
				</div>
			</div>
		</div>
		<?php endif; ?>  
    </div>
 </section>
  <?php foreach ($sections as $section): ?>

							

	  <?php switch($section['kind']):
		  case 1: ?>
		  <!-- Icons Grid -->
		  <section class="features-icons bg-light text-center">
		    <div class="container">
		      <div class="row">
			    <?php foreach ($section['page_items'] as $item): ?>
		        <div class="col-lg-4">
		          <div class="features-icons-item mx-auto mb-5 mb-lg-0 mb-lg-3">
						<?= (!empty($item['url'])) ? "<a href=" . $item['url'] .">" : '' ?>
			            <div class="features-icons-icon d-flex">
			              <i class="fa <?= $item['faicon'] ?> m-auto text-primary"></i>
			            </div>
			            <?= (!empty($item['url'])) ? '</a>' : '' ?>
			            <h3><?= $item['title'] ?></h3>
			            <p class="lead mb-0"><?= $item['description'] ?></p>

		          </div>
		        </div>
		        <?php endforeach; ?>
		      </div>
		    </div>
		  </section>
		<?php break; ?>
		
		<?php case 2: ?>
		  <!-- Image Showcases -->
		  <section class="showcase">
		    <div class="container-fluid p-0">
			  <?php $odd = false; ?>
			  <?php foreach ($section['page_items'] as $item): ?>
		      <div class="row no-gutters">
		        <div class="col-lg-6 <?= ($odd)?'order-lg-2':'';?> text-white showcase-img" style="background-image: url(<?= base_url('media/page_item/' . $item['image_name'] . '.jpg'); ?>);" alt="<?= $item['title'] ?>"></div>
		        <div class="col-lg-6 <?= ($odd)?'order-lg-1':'';?> my-auto showcase-text">
			        
			     <?= (!empty($item['url'])) ? "<a href=" . $item['url'] .">" : '' ?>			        
		          <h2><?= $item['title'] ?></h2>
		          <?= (!empty($item['url'])) ? '</a>' : '' ?>
		          <p class="lead mb-0"><?= $item['description'] ?></p>
		        </div>
		      </div>
		      <?php ($odd) ? $odd = FALSE : $odd = TRUE; ?>
		      
		      <?php endforeach; ?>
		
		    </div>
		  </section>
		<?php break; ?>
		<?php case 3: ?>
		  <!-- Testimonials -->
		  <section class="testimonials text-center bg-light">
		    <div class="container">
		      <h2 class="mb-5"><?= $section['content']?></h2>
		      <div class="row">
			    <?php foreach ($section['page_items'] as $item): ?>
		        <div class="col-lg-4">
		          <div class="testimonial-item mx-auto mb-5 mb-lg-0">

		            <img class="img-fluid rounded-circle mb-3" src="<?= base_url('media/page_item/' . $item['image_name'] . '_thumb.jpg'); ?>" alt="<?= $item['title'] ?>">		            
		            
		            <h5><?= $item['title'] ?></h5>
		            <p class="font-weight-light mb-0">"<?= $item['description'] ?>"</p>
		          </div>
		        </div>
				<?php endforeach; ?>
		      </div>
		    </div>
		  </section>
		<?php break; ?>
		<?php case 4: ?>
		<?php break; ?>
		<?php case 5: ?>
		<section class="">
		    <div class="container"><?= $section['content'] ?></div>
		  </section>
		<?php break; ?>
	<?php endswitch; ?>   
			
  
  <?php endforeach; ?>


  <!-- Call to Action -->
<!--
  <section class="call-to-action text-white text-center">
    <div class="overlay"></div>
    <div class="container">
      <div class="row">
        <div class="col-xl-9 mx-auto">
          <h2 class="mb-4">Ready to get started? Sign up now!</h2>
        </div>
        <div class="col-md-10 col-lg-8 col-xl-7 mx-auto">
          <form>
            <div class="form-row">
              <div class="col-12 col-md-9 mb-2 mb-md-0">
                <input type="email" class="form-control form-control-lg" placeholder="Enter your email...">
              </div>
              <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-block btn-lg btn-primary">Sign up!</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
-->
