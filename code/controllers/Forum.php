<?php
namespace Modular\Controllers;

use ArrayData;
use ArrayList;
use Forum;
use ForumTopic;
use Member;
use Modular\Actions\Approveable;
use Modular\Forms\SocialForm;

class Forum_Controller extends SocialModel {
	private static $model_class = 'Forum';

	// type of approval needed to view.
	private static $approveable_mode = Approveable::ApprovalManual;

	public function init() {
		$this->AuthenticateUser();
		parent::init();
	}

	/**
	 * Default Form for this controller, just calls through to ModelForm.
	 *
	 * @param string $mode
	 * @return SocialForm
	 */
	public function ForumForm($mode) {
		return $this->EditForm($mode);
	}

	/**
	 * Default View for this form, calls through to ModelView.
	 *
	 * @return SocialForm
	 */
	public function ForumView() {
		return $this->ViewForm();
	}

	public function RelatedItems() {
		return new ArrayData([
			'Title'     => singleton('ForumTopic')->plural_name(),
			'ListItems' => ForumTopic::get()->filter([
				'ForumID' => $this()->getModelID(),
			]),
		]);
	}

	/**
	 * Returns the 'All Forums' and 'Following Forums' tabs.
	 *
	 * @return ArrayList
	 */
	public function NavTabBar() {
		return new ArrayList([
			[
				'ID'    => 'allForums',
				'Title' => _t('Groups.AllForumsTabLabel', 'All Forums'),
			],
			[
				'ID'    => 'newForum',
				'Title' => _t('Groups.NewForumTopicTabLabel', 'Create Forum'),
			],

			[
				'ID'    => 'followingForums',
				'Title' => _t('Groups.FollowingForumsTabLabel', 'Following Forums'),
			],
		]);
	}

	/**
	 *
	 * Member following forums
	 *
	 **/
	public function FollowingForums() {
		$member = Member::currentUser();
		$memberForums = $member->RelatedForums();
		$forumArray = [];
		foreach ($memberForums as $item) {
			$forumArray[] = $item->ToForumID;
		}

		return Forum::get()->filter(["ID" => $forumArray]);
	}
}