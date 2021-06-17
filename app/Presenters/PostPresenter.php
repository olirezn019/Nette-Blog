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

        $user = $this->getUser();
        $this->template->user = $user;

        $user_likes = $this->database->table('user_likes');
        $user_liked_post = $user_likes->where('user_id', $user->getIdentity()->id)->where('post_id', $postId)->fetch();
        $this->template->user_liked_post = $user_liked_post;

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
        $form->addSubmit('send', 'Publish comment')->setHtmlAttribute('class', 'ajax');

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
        $this->redrawControl("comments");
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

    public function handleLike($postId, bool $like): void
    {
        $user = $this->getUser();
        $posts = $this->database->table('posts');
        $user_likes = $this->database->table('user_likes');

        // you need to log in to like posts
        if (!$user->isLoggedIn()) {
            $this->flashMessage("You need to be logged in to like posts", 'not success');
            $this->redirect('show', $postId);
        }

        $row = $posts->get($postId);

        $user_liked_post = $user_likes->where('user_id', $user->getIdentity()->id)->where('post_id', $postId)->fetch();

        // if user haven't liked the post yet add it to the database
        if (!$user_liked_post) {
            if ($like)
            {
                $user_likes->insert([
                    'post_id' => $postId,
                    'user_id' => $user->getIdentity()->id,
                    'user_like' => true,
                    'user_dislike' => false
                ]);
                $row->update([
                    'likes' => $row->likes+1
                ]);
            }
            else
            {
                $user_likes->insert([
                    'post_id' => $postId,
                    'user_id' => $user->getIdentity()->id,
                    'user_like' => false,
                    'user_dislike' => true
                ]);
                $row->update([
                    'dislikes' => $row->dislikes+1
                ]);
            }
            $this->redirect('show', $postId);
        }

        // action if user liked the post
        if ($like)
        {
            if (!$user_liked_post->user_like)
            {
                $row->update([
                    'likes' => $row->likes+1
                ]);
                $user_liked_post->update([
                    'user_like' => true
                ]);

                // if user already disliked the post delete the dislike
                if ($user_liked_post->user_dislike)
                {
                    $row->update([
                        'dislikes' => $row->dislikes-1
                    ]);
                    $user_liked_post->update([
                        'user_dislike' => false
                    ]);
                }
            }
            else
            {
                $row->update([
                    'likes' => $row->likes-1
                ]);
                $user_liked_post->update([
                    'user_like' => false
                ]);
            }
        }
        // action if user disliked the post
        else
        {
            if (!$user_liked_post->user_dislike)
            {
                $row->update([
                    'dislikes' => $row->dislikes+1
                ]);
                $user_liked_post->update([
                    'user_dislike' => true
                ]);

                // if user already liked the post delete the like
                if ($user_liked_post->user_like)
                {
                    $row->update([
                        'likes' => $row->likes-1
                    ]);
                    $user_liked_post->update([
                        'user_like' => false
                    ]);
                }
            }
            else
            {
                $row->update([
                    'dislikes' => $row->dislikes-1
                ]);
                $user_liked_post->update([
                    'user_dislike' => false
                ]);
            }
        }

        $this->redrawControl("likes");
    }
}