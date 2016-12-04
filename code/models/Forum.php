<?php
use Modular\Actions\Createable;
use Modular\Actions\Editable;
use Modular\Actions\Listable;
use Modular\Actions\Viewable;
use Modular\Models\SocialModel;

/**
 * A forum public model.
 */
class Forum extends SocialModel implements SocialModelInterface {
	private static $singular_name = 'Forum';

	private static $route_part = 'forum';

	private static $has_many = [
		'ForumTopics' => 'ForumTopic',
		'RelatedMembers' => 'MemberForumAction.ToForum',
	];

	private static $fields_for_mode = [
		Listable::Action => [
			'Title' => true,
			'Replies' => 'ReadonlyField',
			'Views' => 'ReadonlyField',
			'StartedBy' => 'ReadonlyField',
			'LastPost' => 'ReadonlyField',
		],
		Viewable::Action => [
			'Title' => true,
		],
		Editable::Action => [
			'Title' => true,
			'Description' => true,
		],
		Createable::Action => [
			'Title' => true,
			'Description' => true,
		],
	];

	public function StartedBy() {
		if ($created = $this->RelatedMembers()->filter('ActionType.Code', 'MCF')->first()) {
			return $created->FromMember()->Title;
		}
	}

	public function Replies() {
		return Post::get()
			->leftJoin('ForumTopic', 'Post.ForumTopicID = ForumTopic.ID')
			->filter('ForumTopic.ForumID', $this->ID)
			->count();
	}
	public function Views() {
		return 0;
	}
	public function RelatedItems() {
		xdebug_break();
	}

	//Get last topic posted in forum
	public function LastPost() {
		return $this->ForumTopics()->sort("Created", "DESC")->First();
	}

	public function endpoint() {
		return $this->config()->get('route_part');
	}
}