<div>
	<h4>An uncaught Exception was encountered</h4>
	<p>Type: <?= get_class($exception) ?></p>
	<p>Message: <?= $message ?></p>
	<p>Filename: <?= $exception->getFile() ?></p>
	<p>Line Number: <?= $exception->getLine() ?></p>
</div>
