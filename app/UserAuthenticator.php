<?php

namespace App;

use Nette;
use Nette\Security\SimpleIdentity;

class UserAuthenticator implements Nette\Security\Authenticator
{
	private $database;

	public function __construct(Nette\Database\Explorer $database)
    {
		$this->database = $database;
	}

	public function authenticate(string $username, string $password): SimpleIdentity
	{
		$row = $this->database->table('users')->where('username', $username)->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('User not found.');
		}

		if ($password != $row->password) {
			throw new Nette\Security\AuthenticationException('Invalid password.');
		}

		return new SimpleIdentity(
			$row->id,
			$row->role, // nebo pole více rolí
			['name' => $row->username, 'email' => $row->email]
		);
	}
}