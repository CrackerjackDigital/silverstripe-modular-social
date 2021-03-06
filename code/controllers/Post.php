<?php
namespace Modular\Controllers\Social;

use Member;
use Modular\Controllers\SocialModel;
use Modular\Controllers\SocialModelController;
use Modular\Forms\SocialForm;
use Modular\Models\Social\PostReply;

class PostController extends SocialModelController  {
	private static $model_class = 'Modular\Models\Social\Post';

	// type of approval needed to view.
	private static $approveable_mode = \Modular\Actions\Approveable::ApprovalManual;

	private static $allowed_actions = [
		'post_reply',
	];

	public function init() {
		$this->AuthenticateUser();
		parent::init();
	}

	/**
	 * Default View for this form, calls through to ModelView.
	 * @return SocialForm
	 */
	public function PostView() {
		return $this->ViewForm();
	}

	// public function updateFieldsForMode() {

	// }

	/**
	 *
	 * Save post reply
	 *
	 **/
	public function post_reply() {
		$parent_id = (int) $this->request->postVar("parent_post");
		$reply = $this->request->postVar("reply_content");
		if ($parent_id == 0 || empty($reply)) {
			return 0;
		}

		$PostReply = PostReply::create();
		$PostReply->Body = $reply;
		$PostReply->PostID = $parent_id;
		$PostReply->MemberID = Member::currentUserID();
		$PostReply->write();

		//refresh all replies buy fetching new ones
		$PostReplies = PostReply::get()->filter(['PostID' => $parent_id]);
		if ($this->request->isAjax()) {
			return $this->renderWith(['PostReplyList'], compact('PostReplies'));
		}
		return $this->redirect("post/" . $parent_id . "/view");
	}
}