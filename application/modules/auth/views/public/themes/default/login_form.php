<div class="container">
    <div class="row">
        <div class="col-md-4 offset-md-4">
            <div class="card">
	            <h5 class="card-header text-center">Please Sign In</h5>
                <div class="card-body">
                    <?php if ($this->session->flashdata('message')): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <a href="#" class="close" data-dismiss="alert">&times;</a>
                            <?= $this->session->flashdata('message') ?>
                        </div>
                    <?php endif; ?>
                    <form role="form" method="POST" action="<?= base_url('auth/login') ?>">
                        <fieldset>
                            <div class="form-group">
                                <input class="form-control" placeholder="E-mail" name="email" type="email" autofocus>
                            </div>
                            <div class="form-group">
                                <input class="form-control" placeholder="Password" name="password" type="password" value="">
                            </div>
                            <div class="form-check">
						    	<input type="checkbox" class="form-check-input" id="remember" name="remember" value="Remember Me">
						    	<label class="form-check-label" for="remember">Remember Me</label>
							</div>
							<br/>
                            <!-- Change this to a button or input when using this as a form -->
                            <button class="btn btn-lg btn-success btn-block" type="submit">Login</button>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
