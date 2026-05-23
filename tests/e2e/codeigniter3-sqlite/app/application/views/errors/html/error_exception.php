<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<h1>An uncaught Exception was encountered</h1>
<p>Type: <?php echo get_class($exception); ?></p>
<p>Message: <?php echo $message; ?></p>
<p>Filename: <?php echo $exception->getFile(); ?></p>
<p>Line Number: <?php echo $exception->getLine(); ?></p>
