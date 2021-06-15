<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;


class UserPresenter extends Nette\Application\UI\Presenter
{
    private Nette\Database\Explorer $database;
    private $passwords;

	public function __construct(Nette\Database\Explorer $database, Passwords $passwords)
	{
		$this->database = $database;
        $this->passwords = $passwords;
	}

    protected function createComponentChangePasswordForm() : Form
    {
        $form = new Form; // means Nette\Application\UI\Form

        $form->addPassword('password', 'New Password:')->setRequired();
        $form->addPassword('password1', 'New Password Again:')->setRequired();
        $form->addSubmit('send', 'Change password');

        $form->onSuccess[] = [$this, 'changePasswordFormSucceeded'];
        return $form;
    }

    public function changePasswordFormSucceeded(\stdClass $values) : void
    {
        $user = $this->getUser();
        $user->logout();

        if ($values->password != $values->password1) {
            $this->flashMessage('Passwords do not match', 'wrong');
            $this->redirect('this');
        }

        $this->database->table('users')->where('username', $user->getIdentity()->name)->update([
            'password' => $this->passwords->hash($values->password)
        ]);

        $this->flashMessage('Password was successfully changed', 'success');
        $this->redirect('Homepage:default');
    }

    protected function createComponentChangeUsernameForm() : Form
    {
        $form = new Form; // means Nette\Application\UI\Form

        $form->addText('username', 'New Username:')->setRequired();
        $form->addSubmit('send', 'Change password');

        $form->onSuccess[] = [$this, 'changeUsernameFormSucceeded'];
        return $form;
    }

    public function changeUsernameFormSucceeded(\stdClass $values) : void
    {
        $user = $this->getUser();
        $user->logout();

        if (count($this->database->table('users')->where('username', $values->username)) != 0) {
            $this->flashMessage('Username already exists', 'wrong');
            $this->redirect('this');
        }

        $this->database->table('users')->where('username', $user->getIdentity()->name)->update([
            'username' => $values->username
        ]);

        $this->flashMessage('Username was successfully changed', 'success');
        $this->redirect('Homepage:default');
    }
}