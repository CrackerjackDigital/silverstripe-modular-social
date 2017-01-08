<?php
namespace Modular\Controllers;

use ArrayData;
use ArrayList;
use Member;
use Modular\Forms\SocialForm;
use Modular\Models\SocialForum;
use Modular\Models\SocialForumTopic;

class SocialForum_Controller extends SocialModel_Controller {
	private static $model_class = 'Forum';

	// type of approval needed to view.
	private static $approveable_mode = \Modular\Actions\Approveable::ApprovalManual;

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
			'ListItems' => SocialForumTopic::get()->filter([
				                                               'ForumID' => $this()->modelID(),
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
			$forumArray[] = $item->ToModelID;
		}

		return SocialForum::get()->filter(["ID" => $forumArray]);
	}
}