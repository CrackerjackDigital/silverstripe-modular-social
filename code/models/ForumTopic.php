<?php
namespace Modular\Models;

/**
 * A Forum Topic public model.
 */
class SocialForumTopic extends SocialModel
{
	private static $singular_name = 'Forum Topic';

	private static $route_part = 'forumtopic';

	private static $has_one = [
		'Forum' => 'Forum',
	];
	private static $has_many = [
		'Posts'          => 'Post',
		'RelatedMembers' => 'MemberForumTopicAction.ToForumTopic',
	];

	private static $db = [
		'IsClosed' => 'Boolean',
	];

	private static $fields_for_mode = [
		'list' => [
			'Title'      => true,
			'CreatedBy'  => true,
			'Created'    => 'DateField',
			'LastEdited' => 'DateField',
			'PostCount'  => true,
		],
		'view' => [
			'Title'      => true,
			'CreatedBy'  => true,
			'Created'    => 'DateField',
			'LastEdited' => 'DateField',
			'PostCount'  => true,
		],
		'edit' => [
			'Title'       => true,
			'Description' => 'TextareaField',
			'ForumID'     => ['Select2Field', true],
			'FileList'    => 'FileListField',
		],
		'new'  => [
			'Title'       => true,
			'Description' => 'TextareaField',
			'ForumID'     => ['Select2Field', true],
			'Files'       => 'FileAttachmentField',
		],
	];

	public function PostCount() {
		return Post::get()->filter([
			'ForumTopicID' => $this->ID,
		])->count();
	}

	public function StartedBy() {
		$StartedByObj = $this->RelatedMembers()->filter('Type.Code', 'MCT')->first();
		if ($StartedByObj) {
			return $StartedByObj->FromMember();
		}
		return false;
	}

	public function Replies() {
		return $this->PostCount();
	}

	public function Views() {
		return "[Uknown]";
	}

	//Get last topic posted in forum
	public function LastPost() {
		return SocialPost::get()
			->filter([
				'ForumTopicID' => $this->ID,
			])->sort("Created", "DESC")
			->limit(1)
			->first();
	}

	public function canEdit($member = null) {
		$StartedByObj = $this->RelatedMembers()->filter('Type.Code', 'MCT')->first();
		if ($StartedByObj) {
			$ForumTopicOwner = $StartedByObj->FromMemberID;
			if ($ForumTopicOwner == \Member::currentUserID()) {
				return true;
			} else {
				return false;
			}

		}
		return false;
	}

	public function endpoint() {
		return $this->config()->get('route_part');
	}
}