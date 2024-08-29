<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title>
    <?= $this->config->item('project_name') ?>
    <?= ($this->session->has_userdata('page_title')) ? ' | ' . $this->session->userdata('page_title') : "" ?>
  </title>

  <!-- Bootstrap core CSS -->
  <link href="/assets/frontend/default/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

  <!-- Custom fonts for this template -->
  <link href="/assets/frontend/default/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
  <link href="/assets/frontend/default/vendor/simple-line-icons/css/simple-line-icons.css" rel="stylesheet" type="text/css">
  <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700,300italic,400italic,700italic" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template -->
  <link href="/assets/frontend/default/css/landing-page.min.css" rel="stylesheet">
  <link href="/assets/admin/default/css/sb-admin-2.css" rel="stylesheet">

  <!-- CSS Imports -->
  <?php foreach ($this->_css_files as $item) : ?>
    <?= $item ?>
  <?php endforeach; ?>
</head>

<body>

  <!-- Navigation -->
  <nav class="navbar navbar-light bg-light static-top">
    <div class="container">
      <a class="navbar-brand" href="/"><?= $this->config->item('project_name') ?></a>

      <?php if ($this->_is_logged_in) : ?>
        <a class="btn btn-primary" href="<?= base_url('private/profile') ?>">Profile</a>
        <!--       <a class="btn btn-primary" href="<?= base_url('auth/logout') ?>">Sign Out</a> -->
      <?php else : ?>
        <a class="btn btn-primary" href="<?= base_url('auth') ?>">Sign In</a>
      <?php endif; ?>
    </div>
  </nav>

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/">Home</a></li>
      <?php $current_path = ''; ?>
      <?php foreach ($this->uri->segments as $seg) : ?>
        <?php $current_path .= "/$seg"; ?>
        <?php if ($seg == end($this->uri->segments)) : ?>
          <li class="breadcrumb-item active" aria-current="page"><?= ucfirst($seg) ?></li>
        <?php else : ?>
          <li class="breadcrumb-item"> </li>
            <a href="<?= base_url("$current_path") ?>"><?= ucfirst($seg) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
    </ol>
  </nav>