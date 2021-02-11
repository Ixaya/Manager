<style>
.container {
  max-width: 960px;
}

.border-top { border-top: 1px solid #e5e5e5; }
.border-bottom { border-bottom: 1px solid #e5e5e5; }
.border-top-gray { border-top-color: #adb5bd; }

.box-shadow { box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05); }

.lh-condensed { line-height: 1.25; }
</style>

			
 
  <div class="bg-light">

    <div class="container">
	<?php if ($this->session->flashdata('message')): ?>
		<div class="col-lg-12 col-md-12">
			<div class="alert <?=$this->session->flashdata('message-kind')?>  alert-dismissable">
				<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
				<?=$this->session->flashdata('message')?>
			</div>
		</div>
		<?php endif; ?>
      <div class="py-5 text-center">
<!--         <img class="d-block mx-auto mb-4" src="https://getbootstrap.com/docs/4.0/assets/brand/bootstrap-solid.svg" alt="" width="72" height="72"> -->
        <i class="fa fa-user m-auto fa-5x"></i>
        
        <h2>Edit your Profile</h2>
<p class="lead">Below you can edit your Name, Email, Password, and Picture.</p>
<p class="lead">Enjoy! &#128527;</p>
							
      <div class="row">
	      
        <div class="col-md-4 order-md-2 mb-4">
	    <form action="#" method="POST" enctype="multipart/form-data" novalidate>
          <h4 class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted">Profile Picture</span>
            <span class="badge badge-secondary badge-pill"></span>
          </h4>
          <ul class="list-group mb-3">
	        <li class="list-group-item d-flex justify-content-between lh-condensed">
			<div>
                <h6 class="my-0"><?= (empty($user->image_url))? 'No Picture' : 'Current Picture'?></h6>
<!--                 <small class="text-muted">Upload new Picture</small> -->
			</div>
			<span class="text-muted"></span>
			</li>
			<li class="list-group-item d-flex justify-content-between lh-condensed">
			<div>
				<h6><img  height="200px" src="<?= $user->image_url; ?>" /></h6>
				<small class="text-muted">Upload new Picture</small>
                <small><input type="file" name="userfile" size="20" /></small>
				<span class="text-muted"><button class="btn btn-primary btn-xs btn-block" type="submit">Upload Picture</button></span>

            </div>
            </li>
         </ul>
        </div>
        <div class="col-md-8 order-md-1">
          <h4 class="mb-3">Your Name</h4>
          <form class="needs-validation" novalidate>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="firstName">First name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="" value="<?=$user->first_name?>" required>
                <div class="invalid-feedback">
                  Valid first name is required.
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="lastName">Last name</label>
                <input type="text" class="form-control"  id="last_name" name="last_name"  placeholder="" value="<?=$user->last_name?>" required>
                <div class="invalid-feedback">
                  Valid last name is required.
                </div>
              </div>
            </div>

			<div class="row"> 
	            <div class="col-md-6 mb-3">
	              <label for="username">Username</label>
	              <div class="input-group">
	                <div class="input-group-prepend">
	                  <span class="input-group-text">@</span>
	                </div>
	                <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?=$user->username?>" required>
	                <div class="invalid-feedback" style="width: 100%;">
	                  Your username is required.
	                </div>
	              </div>
	            </div>
	
	            <div class="col-md-6 mb-3">
	              <label for="email">Email <span class="text-muted">(Optional)</span></label>
	              <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" value="<?=$user->email?>">
	              <div class="invalid-feedback">
	                Please enter a valid email address for shipping updates.
	              </div>
	            </div>
			</div>
            <hr class="mb-4">

            <h4 class="mb-3">Password</h4>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="" required>
                <small class="text-muted">Enter your password in case you want to change it</small>
              </div>
              <div class="col-md-6 mb-3">
                <label for="password">Confirm your password</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="" required>
                <small class="text-muted"></small>
              </div>
             </div>
            <hr class="mb-4">
            <button class="btn btn-primary btn-lg btn-block" type="submit">Save</button>
          </form>
        </div>
      </div>