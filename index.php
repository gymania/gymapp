<?php
/*
=====================================================
 Данный код защищен авторскими правами
=====================================================
 Файл: login.php 
 -----------------------------------------------------
 Версия: 2.4.1
 2.4.1
 	config get fix
 2.4.0
 	new encrypt pass, auth_key fix
 2.3.1
 	remove session_register
 2.3.0
  	utf8, db clear
 2.2.1
 	remove .
 2.2.0
 	remove social auth
 2.1.1
 	toemail multiauth action
 2.1.0
 	multiauth config setting
 2.0.4
 	host
 2.0.3
 	rewrite auth()
 2.0.2
 	disable button
 2.0.1
 	banned -1, Гость~
-----------------------------------------------------
 Назначение: login
=====================================================
*/

class login extends simple_module
{
	function module_start()
	{
		$this->return['skin'] = "empty";
		
		if($this->user['id']>0)
		{
			$this->utils->location("/");
		}
	}
	
	function index()
	{		
		$cfg = $this->get_config('common');

		$tmpl = new vlibTemplateCache("$this->template/login.html",$this->toptions);
		$tmpl->setvar("config_key",		$cfg['key']);
		$tmpl->setvar("host",		$_SERVER['HTTP_HOST']);
		$this->return['content'] 	= $tmpl->grab();
		$this->return['skin'] 		= "empty";
		
		$_SESSION['redirect'] 		= '/admin/management/';
	}
	
	function auth()
	{
		$email 	= $this->db->clear(trim($this->globals['email']));
		$pass 	= trim($this->globals['pass']);
		
		$errors = array();

		if(!$email)
		{
			$errors[] = "Укажите адрес электронной почты";
			$this->ajax->add_class('label[for=email]', 'error');
			$this->ajax->add_class('#email', 'error');
		}
		elseif(!$this->load_extension('mail')->check_mail($email))
		{
			$errors[] = "Неверный формат электронной почты";
			$this->ajax->add_class('label[for=email]', 'error');
			$this->ajax->add_class('#email', 'error');
		}
		else 
		{
			$this->ajax->remove_class('label[for=email]', 'error');
			$this->ajax->remove_class('#email', 'error');
		}
		
		if(!$pass)
		{
			$errors[] = "Укажите пароль";
			$this->ajax->add_class('label[for=pass]', 'error');
			$this->ajax->add_class('#pass', 'error');
		}
		else 
		{
			$this->ajax->remove_class('label[for=pass]', 'error');
			$this->ajax->remove_class('#pass', 'error');
		}
		
		if(sizeof($errors)==0)
		{
			$cnt = $this->db->exec_sql_one("SELECT COUNT(id) FROM sys_users WHERE email='$email'");
			
			if($cnt==1)
			{
				$res = $this->db->query("SELECT sys_users.id, sys_users.email, sys_users.pass, sys_users.is_active, sys_users_authorization.auth_id, sys_users_authorization.auth_key
																FROM sys_users 
																LEFT JOIN sys_users_authorization ON sys_users.id=sys_users_authorization.user_id 
																WHERE email='$email'");
				$row = $this->db->fetch_object($res);
	
				$hash = $this->utils->encrypt($pass);
				
				if($hash==$row->pass)
				{
						if($row->is_active==1 || $row->is_active==0)
						{
							$time 		= time();
							$life_time = $time+(3600*24*999);
							
							$cfg = $this->get_config('common');
							
							if(!$cfg['multiauth'])
							{
								$new_id		= $this->utils->encrypt($row->id.$row->email.$time);
								$new_key	= $this->utils->encrypt($new_id);
								$this->db->query("UPDATE sys_users_authorization
										  SET 
										  	network='',
										  	network_uid='',
										  	network_identity='',
										  	network_profile='',
										  	auth_id='$new_id',
										  	auth_key='$new_key',
										  	auth_key_date='$time',
										  	last_login='$time' 
										  WHERE user_id='$row->id'");
								
								setcookie("code", $new_id, $life_time, "/");
							   	setcookie("stronghold", $new_key, $life_time, "/");
							}
							else 
							{
								setcookie("code", $row->auth_id, $life_time, "/");
							   	setcookie("stronghold", $row->auth_key, $life_time, "/");
								
								$this->db->query("UPDATE sys_users_authorization
										  SET 
										  	network='',
										  	network_uid='',
										  	network_identity='',
										  	network_profile='',
										  	last_login='$time' 
										  WHERE user_id='$row->id'");
							}
										   	 	
							$_SESSION['user_id']	= $row->id;
							$_SESSION['user_pass']	= $row->pass;						
							
							$this->ajax->html('form p', 'Вы успешно авторизованы');
							$this->ajax->add_class('form p', 'ok');
							$this->ajax->remove_class('form p', 'error');
								
							if($_SESSION['redirect'])
							{
								$this->ajax->timer("_location('{$_SESSION['redirect']}');" , 1500);
								$_SESSION['redirect'] = '';
							}
							else 
							{
								$this->ajax->timer('window.location.reload(false);', 1500);
							}
	
							if($row->is_active==0)
							{
								$this->db->query("UPDATE sys_users SET is_active='1' WHERE id='$row->id'");
							}
						}
						else
						{
							$errors[] = "Учетная запись заблокирована!";
						}
				}
				else 
				{
					$errors[] = "Неверно указана электронная почта или&nbsp;пароль";
				}
			}
			else 
			{
				$errors[] = "Неверно указана электронная почта или&nbsp;пароль";
			}
		}
		
		if(sizeof($errors)>0)
		{
			$this->ajax->html('form p', implode("<br/>", $errors));
			$this->ajax->remove_class('form p', 'ok');
			$this->ajax->add_class('form p', 'error');
			$this->ajax->timer('$("input[type=submit]").val("Войти").removeAttr("disabled");', 10);
		}
		
		$errors[] = "Неверно указана электронная почта или&nbsp;пароль";
		$errors[] = "Неверно указана электронная почта или&nbsp;пароль";
	}
}
?>