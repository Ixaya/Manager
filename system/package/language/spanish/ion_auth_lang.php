<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Name:  Ion Auth Lang - Spanish
 *
 * Author: Wilfrido Garc�a Espinosa
 * 		  contacto@wilfridogarcia.com
 *		 @wilfridogarcia
 *
 * Location: http://github.com/benedmunds/ion_auth/
 *
 * Created:  05.04.2010
 *
 * Description:  Spanish language file for Ion Auth messages and errors
 *
 */

// Account Creation
$lang['account_creation_successful'] 	  	 = 'Cuenta creada exitosamente';
$lang['account_creation_unsuccessful'] 	 	 = 'No se pudo crear la cuenta';
$lang['account_creation_duplicate_email'] 	 = 'Email no válido o en uso';
$lang['account_creation_duplicate_username'] = 'Nombre de usuario no válido o en uso';

$lang['account_creation_missing_default_group'] = 'No se ha definido el grupo por defecto';
$lang['account_creation_invalid_default_group'] = 'El nombre del grupo por defecto no es válido';


// Password
$lang['password_change_successful'] 	 	 = 'Contraseña restablecida exitosamente';
$lang['password_change_unsuccessful'] 	  	 = 'No se pudo cambiar la contraseña';
$lang['forgot_password_successful'] 	 	 = 'Nueva contraseña enviada por correo electrónico';
$lang['forgot_password_unsuccessful'] 	 	 = 'No se pudo crear una nueva contraseña';

// Activation
$lang['activate_successful'] 		  		 = 'Cuenta activada exitosamente';
$lang['activate_unsuccessful'] 		 		 = 'No se pudo activar la cuenta';
$lang['deactivate_successful'] 		  		 = 'Cuenta desactivada exitosamente';
$lang['deactivate_unsuccessful'] 	  		 = 'No se pudo desactivar la cuenta';
$lang['activation_email_successful'] 	  	 = 'Correo electrónico de activación enviado';
$lang['activation_email_unsuccessful']   	 = 'No se pudo enviar el correo electrónico de activación';

// Login / Logout
$lang['login_successful'] 			  		 = 'Sesión iniciada exitosamente';
$lang['login_unsuccessful'] 		  		 = 'No se pudo iniciar sesión';
$lang['login_unsuccessful_not_active'] 		 = 'La cuenta está inactiva';
$lang['login_timeout']					   = 'Bloqueo temporal. Intenta nuevamente más tarde.';
$lang['logout_successful'] 		 			 = 'Sesión finalizada exitosamente';

// Account Changes
$lang['update_successful'] 		 			 = 'Información de la cuenta actualizada exitosamente';
$lang['update_unsuccessful'] 		 		 = 'No se pudo actualizar la información de la cuenta';
$lang['delete_successful'] 		 			 = 'Usuario eliminado';
$lang['delete_unsuccessful'] 		 		 = 'No se pudo eliminar al usuario';

// Groups
$lang['group_creation_successful']  = 'Grupo creado exitosamente';
$lang['group_already_exists']	   = 'El nombre del grupo ya está en uso';
$lang['group_update_successful']	= 'Datos del grupo actualizados';
$lang['group_delete_successful']	= 'Grupo eliminado';
$lang['group_delete_unsuccessful'] 	= 'No se pudo eliminar el grupo';
$lang['group_delete_notallowed']	= 'No se puede eliminar el grupo de administradores';
$lang['group_name_required'] 		= 'El nombre del grupo es obligatorio';
$lang['group_name_admin_not_alter'] = 'No se puede cambiar el nombre del grupo de administradores';

// Activation Email
$lang['email_activation_subject']			= 'Activación de la cuenta';
$lang['email_activate_heading']	= 'Activar cuenta por %s';
$lang['email_activate_subheading'] = 'Por favor, ingresa en este enlace para %s.';
$lang['email_activate_link']	   = 'activar tu cuenta';
// Forgot Password Email
$lang['email_forgotten_password_subject']	= 'Verificación de contraseña olvidada';
$lang['email_forgot_password_heading']	= 'Restablecer contraseña para %s';
$lang['email_forgot_password_subheading'] = 'Por favor, ingresa en este enlace para %s.';
$lang['email_forgot_password_link']	   = 'restablecer tu contraseña';
// New Password Email
$lang['email_new_password_subject']		  = 'Nueva contraseña';
$lang['email_new_password_heading']	= 'Nueva contraseña para %s';
$lang['email_new_password_subheading'] = 'Tu contraseña ha sido restablecida a: %s';
