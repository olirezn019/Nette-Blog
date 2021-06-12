<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

class RegisterPresenter extends Nette\Application\UI\Presenter
{
    private Nette\Database\Explorer $database;

	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}

    protected function createComponentRegisterForm(): Form
    {
        $form = new Form; // means Nette\Application\UI\Form

        $form->addText('username', 'Username:')->setRequired();
        $form->addEmail('email', 'Email:')->setRequired();
        $form->addPassword('password', 'Password: ')->setRequired();
        $form->addPassword('password1', 'Password again: ')->setRequired();
        $form->addSubmit('send', 'Create account');

        $form->onSuccess[] = [$this, 'registerFormSucceeded'];

        return $form;
    }

    public function registerFormSucceeded(\stdClass $values): void
    {
        $users_table = $this->database->table('users');
        
        // check if passwords match
        if ($values->password != $values->password1) {
            $this->flashMessage('Passwords do not match', 'wrong');
            $this->redirect('this');
        }
        // check if username is in the database
        else if (count($users_table->where('username', $values->username)) != 0) {
            $this->flashMessage('Username already exists', 'wrong');
            $this->redirect('this');
        }
        // check if email is taken
        else if (count($users_table->where('email', $values->email)) != 0) {
            $this->flashMessage('This email is already registered', 'wrong');
            $this->redirect('this');
        }
        else {
            // add new user in the database
            $users_table->insert([
                'username' => $values->username,
                'role' => "user",
                'email' => $values->email,
                'password' => $values->password,
            ]);
    
            $this->flashMessage('You have been succesfully registered', 'success');
            $this->redirect('this');
        }
    }
}