<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = PhpCsFixer\Finder::create()
	->in([
		__DIR__ . '/application/',
		__DIR__ . '/editions/'
	])
	->name('*.php')
	->ignoreDotFiles(true)
	->ignoreVCS(true);

$config = new PhpCsFixer\Config();

$config->setRules([
	'@PSR12' => true,
	'array_syntax' => ['syntax' => 'short'],
	'indentation_type' => true,
	'single_blank_line_at_eof' => true,
	'no_whitespace_before_comma_in_array' => true,
	'whitespace_after_comma_in_array' => true,
	'trim_array_spaces' => true,
	'no_break_comment' => false,
])
	->setRiskyAllowed(true)
	->setIndent("\t")
	->setLineEnding("\n")
	->setFinder($finder);

return $config;
