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
	    //$this->template->comments = $post->related('comments')->order('created_at');
	    $this->template->comments = $this->database->table('comments')->where('post_id', $postId)->order('created_at');
    }

    protected function createComponentCommentForm(): Form
    {
        $form = new Form; // means Nette\Application\UI\Form
        $user = $this->getUser();

        if (!$user->isLoggedIn()) {
            $form->addText('name', 'Your name:')->setRequired();
            $form->addEmail('email', 'Email:');
        }
        $form->addTextArea('content', 'Comment:')->setRequired();
        $form->addSubmit('send', 'Publish comment');

        $form->onSuccess[] = [$this, 'commentFormSucceeded'];
        return $form;
    }

    public function commentFormSucceeded(\stdClass $values): void
    {
        $postId = $this->getParameter('postId');
        $user = $this->getUser();

        if ($user->isLoggedIn()) {
            $this->database->table('comments')->insert([
                'post_id' => $postId,
                'name' => $user->getIdentity()->name,
                'email' => $user->getIdentity()->email,
                'content' => $values->content,
            ]);
        }
        else {
            $this->database->table('comments')->insert([
                'post_id' => $postId,
                'name' => $values->name,
                'email' => $values->email,
                'content' => $values->content,
            ]);
        }

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

    public function postFormSucceeded(\stdClass $values): void
    {
        $postId = $this->getParameter('postId');
        $user = $this->getUser();

        if ($postId) {
            $post = $this->database->table('posts')->get($postId);
            $post->update($values);
        } else {
            $post = $this->database->table('posts')->insert([
                'title' => $values->title,
                'content' => $values->content,
                'created_by' => $user->getIdentity()->name
            ]);
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
        $user = $this->getUser();

        if (!$post) {
            $this->error('Post not found');
        }
        // If you didn't create the post or you aren't admin you can't edit it
        else if ($post->created_by != $user->getIdentity()->name && $user->getIdentity()->name != "admin") {
            $this->flashMessage("You don't have permission to edit this post", 'not success');
            $this->redirect('show', $post->id);
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