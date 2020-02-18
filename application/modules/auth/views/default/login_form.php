<!-- Testimonials -->
<section class="testimonials text-center bg-light">
<div class="container">
  <h2 class="mb-5">Please Sign In ...</h2>
  

	<?php if ($this->session->flashdata('message')): ?>
	<div class="row">
		<div class="col-md-6 offset-md-3">
			<div class="alert alert-warning alert-dismissible fade show" role="alert">
				<a href="#" class="close" data-dismiss="alert">&times;</a>
				<?= $this->session->flashdata('message') ?>
			</div>
		</div>
	</div>
	<?php endif; ?>  
  <div class="row">
    <div class="col-lg-4 offset-md-2">
		<div class="col-md-12 offset-md-12">
			<div class="card">
				<h5 class="card-header text-center">Sign In</h5>
				<div class="card-body">
					<form role="form" method="POST" action="<?= base_url('auth/login') ?>">
						<fieldset>
							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<input class="form-control" placeholder="E-mail" name="email" type="email" autofocus>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<input class="form-control" placeholder="Password" name="password" type="password" value="">
									</div>
								</div>	
							</div>
							<div class="row">
								<div class="col-md-12">
									<div class="form-check">
										<input type="checkbox" class="form-check-input" id="remember" name="remember" value="Remember Me">
										<label class="form-check-label" for="remember">Remember Me</label>
									</div>
								</div>
							</div>
							<div class="row">
								<br> <!-- Just Add Space -->
							</div>
							<div class="row">
								<div class="col-md-12">
									<button class="btn btn-lg btn-success btn-block" type="submit">Sign In</button>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
			</div>
		</div>
    </div>
    <div class="col-lg-5">
		<div class="col-12">
			<div class="card">
				<h5 class="card-header text-center">Or Sign Up</h5>
				<div class="card-body">
					<form role="form" method="POST" action="<?= base_url('auth/signup') ?>">
						<fieldset>
							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<input class="form-control" placeholder="E-mail" name="email" type="email" autofocus>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<input class="form-control" placeholder="First Name" name="first_name" type="input" autofocus>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<input class="form-control" placeholder="Last Name" name="last_name" type="input" autofocus>
									</div>
								</div>
							</div>
							
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<input class="form-control" placeholder="Password" name="password" type="password" value="">
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<input class="form-control" placeholder="Confirm Password" name="retry" type="password" value="">
									</div>
								</div>
							</div>
							<div class="row">
								<button class="btn btn-lg btn-success btn-block" type="submit">Sign Up</button>
							</div>
						</fieldset>
					</form>
				</div>
			</div>
		</div>
    </div>
  </div>
</div>
</section>