<?php
namespace Modular\Controllers\Social;

use ArrayData;
use ArrayList;
use HiddenField;
use Modular\Controllers\SocialModel;
use Modular\Forms\SocialForm;
use Modular\Models\Social\Post;

class ForumTopic_ extends SocialModel {
	private static $model_class = 'ForumTopic';

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
	public function ForumTopicForm($mode) {
		return $this->EditForm($mode);
	}

	/**
	 * Default View for this form, calls through to ModelView.
	 *
	 * @return SocialForm
	 */
	public function ForumTopicView() {
		return $this->ViewForm();
	}

	public function RelatedItems() {
		return new ArrayData([
			'Title'     => 'Topics',
			'ListItems' => Post::get()->filter('ForumTopicID', $this->getModelID()),
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
				'ID'    => 'allForumTopics',
				'Title' => _t('Groups.AllForumTopicsTabLabel', 'All Forum Topics'),
			],
			[
				'ID'    => 'newForumTopic',
				'Title' => _t('Groups.NewForumTopicTabLabel', 'Create Forum Topic'),
			],

			[
				'ID'    => 'followingForums',
				'Title' => _t('Groups.FollowingForumsTabLabel', 'Following Topics'),
			],
		]);
	}

	public function ForumPostableForm() {
		$form = $this->PostableForm();
		if ($form) {
			$form->Fields()->push(HiddenField::create('ForumTopicID', '', $this->getModelID()));
			$form->Fields()->removeByName('Images');
		}
		return $form;
	}

}