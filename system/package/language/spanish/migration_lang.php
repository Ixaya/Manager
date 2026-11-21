<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * System messages translation for CodeIgniter(tm)
 *
 * @author	CodeIgniter community
 * @author	Iban Eguia
 * @copyright	Copyright (c) 2014 - 2017, British Columbia Institute of Technology (http://bcit.ca/)
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 */

$lang['migration_none_found']		= 'No se encontró ninguna migración.';
$lang['migration_not_found']		= 'No se encontró ninguna migración con el número de versión: %s.';
$lang['migration_sequence_gap']		= 'Hay un vacío en la migración, cercano al número de versión: %s.';
$lang['migration_multiple_version']	= 'Hay múltiples migraciones con el mismo número de versión: %s.';
$lang['migration_class_doesnt_exist']	= 'La clase de migración "%s" no pudo ser encontrada.';
$lang['migration_missing_up_method']	= 'Le falta el método "up" a la clase de migración "%s".';
$lang['migration_missing_down_method']	= 'Le falta el método "down" a la clase de migración "%s".';
$lang['migration_invalid_filename']	= 'La migración "%s" tiene un nombre de archivo no válido.';
