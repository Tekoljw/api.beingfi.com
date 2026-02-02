<?php
namespace Common\Model;

class UserModel extends \Think\Model
{
	public function check_install()
	{
		$this->check_authorization();
		$this->check_database();
		$this->check_update();
	}

	public function check_uninstall()
	{
	}

	public function check_server()
	{
	}

	public function check_authorization()
	{
	}

	public function check_database()
	{
	}

	public function check_update()
	{
		$check_update_user = (APP_DEBUG ? null : S('check_update_user'));

		if (!$check_update_user) {
			$User_DbFields = M('User')->getDbFields();

			//用户表里面不存在email字段，则添加
			if (!in_array('email', $User_DbFields)) {
				M()->execute('ALTER TABLE `tw_user` ADD COLUMN `email` VARCHAR(200) NULL  COMMENT \'邮箱\' AFTER `status`;');
			}

			S('check_update_user', 1);
		}
	}

	public function get_userid($username = NULL)
	{
		if (empty($username)) {
			return null;
		}

		$get_userid_user = (APP_DEBUG ? null : S('get_userid_user' . $username));

		if (!$get_userid_user) {
			$get_userid_user = M('User')->where(array('username' => $username))->getField('id');
			S('get_userid_user' . $username, $get_userid_user);
		}

		return $get_userid_user;
	}

	public function get_username($id = NULL)
	{
		if (empty($id)) {
			return null;
		}

		$user = (APP_DEBUG ? null : S('get_username' . $id));

		if (!$user) {
			$user = M('User')->where(array('id' => $id))->getField('username');
			S('get_username' . $id, $user);
		}

		return $user;
	}
}

?>