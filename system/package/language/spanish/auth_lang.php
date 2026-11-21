<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Name:  Auth Lang - English
 *
 * Author: Ben Edmunds
 * 		  ben.edmunds@gmail.com
 *		 @benedmunds
 *
 * Author: Daniel Davis
 *		 @ourmaninjapan
 *
 * Author: Josue Ibarra
 *		 @josuetijuana
 *
 * Location: http://github.com/benedmunds/ion_auth/
 *
 * Created:  03.09.2013
 *
 * Description:  Spanish language file for Ion Auth example views
 *
 */

// Errors
$lang['error_csrf'] = 'Este formulario no pasó nuestras pruebas de seguridad.';

// Login
$lang['login_heading']		 = 'Ingresar';
$lang['login_subheading']	  = 'Por favor, introduce tu email/usuario y contraseña.';
$lang['login_identity_label']  = 'Email/usuario:';
$lang['login_password_label']  = 'Contraseña:';
$lang['login_remember_label']  = 'Recuérdame:';
$lang['login_submit_btn']	  = 'Ingresar';
$lang['login_forgot_password'] = '¿Olvidaste tu contraseña?';

// Index
$lang['index_heading']		   = 'Usuarios';
$lang['index_subheading']		= 'La lista de usuarios está debajo.';
$lang['index_fname_th']		  = 'Nombre';
$lang['index_lname_th']		  = 'Apellidos';
$lang['index_email_th']		  = 'Email';
$lang['index_groups_th']		 = 'Grupos';
$lang['index_status_th']		 = 'Estado';
$lang['index_action_th']		 = 'Acción';
$lang['index_active_link']	   = 'Activo';
$lang['index_inactive_link']	 = 'Inactivo';
$lang['index_create_user_link']  = 'Crear nuevo usuario';
$lang['index_create_group_link'] = 'Crear nuevo grupo';

// Deactivate User
$lang['deactivate_heading']				  = 'Desactivar usuario';
$lang['deactivate_subheading']			   = '¿Estás seguro de que quieres desactivar al usuario \'%s\'';
$lang['deactivate_confirm_y_label']		  = 'Sí:';
$lang['deactivate_confirm_n_label']		  = 'No:';
$lang['deactivate_submit_btn']			   = 'Enviar';
$lang['deactivate_validation_confirm_label'] = 'confirmación';
$lang['deactivate_validation_user_id_label'] = 'ID de usuario';

// Create User
$lang['create_user_heading']						   = 'Crear usuario';
$lang['create_user_subheading']						= 'Por favor, introduce la información del usuario.';
$lang['create_user_fname_label']					   = 'Nombre:';
$lang['create_user_lname_label']					   = 'Apellidos:';
$lang['create_user_company_label']					 = 'Compañía:';
$lang['create_user_email_label']					   = 'Email:';
$lang['create_user_phone_label']					   = 'Teléfono:';
$lang['create_user_password_label']					= 'Contraseña:';
$lang['create_user_password_confirm_label']			= 'Confirmar contraseña:';
$lang['create_user_submit_btn']						= 'Crear usuario';
$lang['create_user_validation_fname_label']			= 'Nombre';
$lang['create_user_validation_lname_label']			= 'Apellidos';
$lang['create_user_validation_email_label']			= 'Correo electrónico';
$lang['create_user_validation_phone1_label']		   = 'Primera parte del número de teléfono';
$lang['create_user_validation_phone2_label']		   = 'Segunda parte del número de teléfono';
$lang['create_user_validation_phone3_label']		   = 'Tercera parte del número de teléfono';
$lang['create_user_validation_company_label']		  = 'Nombre de la compañía';
$lang['create_user_validation_password_label']		 = 'Contraseña';
$lang['create_user_validation_password_confirm_label'] = 'Confirmación de contraseña';

// Edit User
$lang['edit_user_heading']						   = 'Editar usuario';
$lang['edit_user_subheading']						= 'Por favor, introduce la nueva información del usuario.';
$lang['edit_user_fname_label']					   = 'Nombre:';
$lang['edit_user_lname_label']					   = 'Apellidos:';
$lang['edit_user_company_label']					 = 'Compañía:';
$lang['edit_user_email_label']					   = 'Email:';
$lang['edit_user_phone_label']					   = 'Teléfono:';
$lang['edit_user_password_label']					= 'Contraseña: (solamente si quieres cambiarla)';
$lang['edit_user_password_confirm_label']			= 'Confirmar contraseña: (solamente si quieres cambiarla)';
$lang['edit_user_groups_heading']					= 'Miembro de grupos';
$lang['edit_user_submit_btn']						= 'Guardar usuario';
$lang['edit_user_validation_fname_label']			= 'Nombre';
$lang['edit_user_validation_lname_label']			= 'Apellidos';
$lang['edit_user_validation_email_label']			= 'Correo electrónico';
$lang['edit_user_validation_phone1_label']		   = 'Primera parte del número de teléfono';
$lang['edit_user_validation_phone2_label']		   = 'Segunda parte del número de teléfono';
$lang['edit_user_validation_phone3_label']		   = 'Tercera parte del número de teléfono';
$lang['edit_user_validation_company_label']		  = 'Compañía';
$lang['edit_user_validation_groups_label']		   = 'Grupos';
$lang['edit_user_validation_password_label']		 = 'Contraseña';
$lang['edit_user_validation_password_confirm_label'] = 'Confirmación de contraseña';

// Create Group
$lang['create_group_title']				  = 'Crear grupo';
$lang['create_group_heading']				= 'Crear grupo';
$lang['create_group_subheading']			 = 'Por favor, introduce la información del grupo.';
$lang['create_group_name_label']			 = 'Nombre del grupo:';
$lang['create_group_desc_label']			 = 'Descripción:';
$lang['create_group_submit_btn']			 = 'Crear grupo';
$lang['create_group_validation_name_label']  = 'Nombre del grupo';
$lang['create_group_validation_desc_label']  = 'Descripcion';

// Edit Group
$lang['edit_group_title']				  = 'Editar grupo';
$lang['edit_group_saved']				  = 'Grupo guardado';
$lang['edit_group_heading']				= 'Editar grupo';
$lang['edit_group_subheading']			 = 'Por favor, registra la informacion del grupo.';
$lang['edit_group_name_label']			 = 'Nombre del grupo:';
$lang['edit_group_desc_label']			 = 'Descripción:';
$lang['edit_group_submit_btn']			 = 'Guardar grupo';
$lang['edit_group_validation_name_label']  = 'Nombre del grupo';
$lang['edit_group_validation_desc_label']  = 'Descripción';

// Change Password
$lang['change_password_heading']							   = 'Cambiar contraseña';
$lang['change_password_old_password_label']					= 'Contraseña anterior:';
$lang['change_password_new_password_label']					= 'Nueva contraseña (de por lo menos %s caracteres de longitud):';
$lang['change_password_new_password_confirm_label']			= 'Confirmar nueva contraseña:';
$lang['change_password_submit_btn']							= 'Cambiar';
$lang['change_password_validation_old_password_label']		 = 'Contraseña anterior';
$lang['change_password_validation_new_password_label']		 = 'Nueva contraseña';
$lang['change_password_validation_new_password_confirm_label'] = 'Confirmar nueva contraseña';

// Forgot Password
$lang['forgot_password_heading']				 = 'Olvidé mi contraseña';
$lang['forgot_password_subheading']			  = 'Por favor, introduce tu %s para que podamos enviarte un email para restablecer tu contraseña.';
$lang['forgot_password_email_label']			 = '%s:';
$lang['forgot_password_submit_btn']			  = 'Enviar';
$lang['forgot_password_validation_email_label']  = 'Correo electrónico';
$lang['forgot_password_username_identity_label'] = 'Usuario';
$lang['forgot_password_email_identity_label']	= 'Email';
$lang['forgot_password_email_not_found']		 = 'El correo electrónico no existe.';

// Reset Password
$lang['reset_password_heading']							   = 'Cambiar contraseña';
$lang['reset_password_new_password_label']					= 'Nueva contraseña (de por lo menos %s caracteres de longitud):';
$lang['reset_password_new_password_confirm_label']			= 'Confirmar nueva contraseña:';
$lang['reset_password_submit_btn']							= 'Guardar';
$lang['reset_password_validation_new_password_label']		 = 'Nueva contraseña';
$lang['reset_password_validation_new_password_confirm_label'] = 'Confirmar nueva contraseña';

// Activation Email
$lang['email_activate_heading']	= 'Activar cuenta por %s';
$lang['email_activate_subheading'] = 'Por favor, ingresa en este enlace para %s.';
$lang['email_activate_link']	   = 'activar tu cuenta';

// Forgot Password Email
$lang['email_forgot_password_heading']	= 'Restablecer contraseña para %s';
$lang['email_forgot_password_subheading'] = 'Por favor, ingresa en este enlace para %s.';
$lang['email_forgot_password_link']	   = 'restablecer tu contraseña';

// New Password Email
$lang['email_new_password_heading']	= 'Nueva contraseña para %s';
$lang['email_new_password_subheading'] = 'Tu contraseña ha sido restablecida a: %s';
