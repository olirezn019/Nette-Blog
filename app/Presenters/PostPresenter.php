<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

class PostPresenter extends Nette\Application\UI\Presenter
{
	private Nette\Database\Explorer $database;

	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}

    public function renderShow(int $postId): void
    {
        $post = $this->database->table('posts')->get($postId);
        if (!$post) {
            $this->error('Post not found');
        }

        $this->template->post = $post;
        // Add comments into the page
	    $this->template->comments = $post->related('comments')->order('created_at');
    }

    protected function createComponentCommentForm(): Form
    {
        $form = new Form; // means Nette\Application\UI\Form

        $form->addText('name', 'Your name:')
            ->setRequired();

        $form->addEmail('email', 'Email:');

        $form->addTextArea('content', 'Comment:')
            ->setRequired();

        $form->addSubmit('send', 'Publish comment');

        $form->onSuccess[] = [$this, 'commentFormSucceeded'];
        return $form;
    }

    public function commentFormSucceeded(\stdClass $values): void
    {
        $postId = $this->getParameter('postId');

        $this->database->table('comments')->insert([
            'post_id' => $postId,
            'name' => $values->name,
            'email' => $values->email,
            'content' => $values->content,
        ]);

        $this->flashMessage('Thank you for your comment', 'success');
        $this->redirect('this');
    }

    protected function createComponentPostForm(): Form
    {
        $form = new Form;

        $form->addText('title', "Title:")->setRequired();
        $form->addTextArea('content', 'Content:')->setRequired();
        $form->addSubmit('send', "Save and Publish");

        $form->onSuccess[] = [$this, 'postFormSucceeded'];

        return $form;
    }

    public function postFormSucceeded(array $values): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('You need to log in to create or edit posts');
        }
        
        $postId = $this->getParameter('postId');

        if ($postId) {
            $post = $this->database->table('posts')->get($postId);
            $post->update($values);
        } else {
            $post = $this->database->table('posts')->insert($values);
        }

        $this->flashMessage('Post was published', 'success');
        $this->redirect('show', $post->id);
    }

    public function actionEdit(int $postId): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
        $post = $this->database->table('posts')->get($postId);
        if (!$post) {
            $this->error('Post not found');
        }
        $this['postForm']->setDefaults($post->toArray());
    }

    public function actionCreate(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }
}