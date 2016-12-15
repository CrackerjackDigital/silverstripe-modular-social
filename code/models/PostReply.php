<?php
namespace Modular\Models\Social;
use Modular\Models\SocialModel;

/**
 *
 */
class PostReply extends SocialModel {

	private static $approveable_mode = \Modular\Actions\Approveable::ApprovalAutomatic;

	private static $db = [
		'Body' => 'Text',
	];

	private static $has_one = [
		'Post' => 'Post',
		'Member' => 'Member',
	];

	private static $singular_name = 'Post Reply';

	private static $plural_name = 'Post Replies';

	private static $route_part = 'post-reply';

	private static $summary_fields = [
		'Body' => 'Body',
	];

	public function getFieldsForMode($mode) {
		return \Application::get_config_setting('fields_for_mode', $mode, __CLASS__);
	}

	public function endpoint() {
		return $this->config()->get('route_part');
	}

}