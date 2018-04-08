<?php
/*
|--------------------------------------------------------------------------
| Image Preset Sizes
|--------------------------------------------------------------------------
|
| Specify the preset sizes you want to use in your code. Only these preset 
| will be accepted by the controller for security.
|
| Each preset exists of a width and height. If one of the dimensions are 
| equal to 0, it will automatically calculate a matching width or height 
| to maintain the original ratio.
|
| If both dimensions are specified it will automatically crop the 
| resulting image so that it fits those dimensions.
|
*/

// examples
$config["image_sizes"]["square"] = array(100, 100);
$config["image_sizes"]["long"]   = array(280, 600);
$config["image_sizes"]["wide"]   = array(600, 200);
$config["image_sizes"]["hero"]   = array(940, 320);

$config["image_sizes"]["small"]  = array(280, 0);
$config["image_sizes"]["medium"] = array(340, 0);
$config["image_sizes"]["large"]  = array(800, 0);
$config["image_sizes"]["og_thumb"]  = array(960, 504);


// ixayanet admin
$config["image_sizes"]["admin-list"]  = array(0, 32);
$config["image_sizes"]["admin-edit"]  = array(0, 128);
// $config["image_sizes"]["mobile-small"]  = array(0, 32);
// $config["image_sizes"]["mobile-small"]  = array(0, 32);

// ixayanet mobile
// $config["image_sizes"]["mobile-small"]  = array(0, 32);
// $config["image_sizes"]["mobile-small"]  = array(0, 32);
// $config["image_sizes"]["mobile-small"]  = array(0, 32);

