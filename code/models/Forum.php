<?php
namespace Modular\Models;

/**
 * A forum public model.
 */
class SocialForum extends SocialModel {
	private static $singular_name = 'Forum';

	private static $route_part = 'forum';

	private static $has_many = [
		'ForumTopics' => 'SocialForumTopic',
		'RelatedMembers' => 'MemberForum.ToModel',
	];

	private static $fields_for_mode = [
		\Modular\Actions\Listable::Action => [
			'Title' => true,
			'Replies' => 'ReadonlyField',
			'Views' => 'ReadonlyField',
			'StartedBy' => 'ReadonlyField',
			'LastPost' => 'ReadonlyField',
		],
		\Modular\Actions\Viewable::Action => [
			'Title' => true,
		],
		\Modular\Actions\Editable::Action => [
			'Title' => true,
			'Description' => true,
		],
		\Modular\Actions\Createable::Action => [
			'Title' => true,
			'Description' => true,
		],
	];

	public function StartedBy() {
		if ($created = $this->RelatedMembers()->filter('Type.Code', 'MCF')->first()) {
			return $created->FromModel()->Title;
		}
	}

	public function Replies() {
		return SocialPost::get()
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