<?php
/**
 * Preference extends SocialModel
 * Member account settings / Preference
 *
 */
namespace Modular\Actions;

use ArrayData;
use ArrayList;
use DataObject;
use Member;
use Modular\Edges\MemberRssFeed;
use Modular\Extensions\Controller\SocialAction;
use Modular\Extensions\Model\SocialMember;
use Modular\Forms\SocialForm;
use RSSFeed;
use SS_HTTPRequest;
use SS_HTTPResponse;
use ValidationException;
use Modular\Exceptions\Social as Exception;

class Preference extends SocialAction {
	// Re-use the edit code here for permissions etc
	const ActionCode = 'EDT';
	// url for action, e.g. 'post'
	const ActionName = 'settings';

	private static $url_handlers = [
		'$ID/settings'   => self::ActionName,
		'interests-json' => "interests_json",
	];
	private static $allowed_actions = [
		self::ActionName => '->canEdit("action")',
		'interests_json' => '->canEdit("action")',
	];

	private static $action_templates = [
		self::ActionName => self::ActionName,
	];

	private static $action_modes = [
		self::ActionName => self::ActionName,
	];

	/**
	 * Checks member is logged in and:
	 *
	 * - if logged in member then need to have 'EDT' permissions
	 * - a 'CRT' record needs to exist in the past in the relationship instance table
	 *
	 * @param null $member
	 * @return bool
	 */
	public function canEdit($source = null) {
		// check we have permission, are admin, have a previous 'CRT' record
		return parent::canDoIt(self::ActionCode, $source);
	}

	/**
	 * Return true if the action has been taken (e.g. a 'follow' action), false if not or null if not the action that
	 * this extension implements.
	 *
	 * @param $action
	 * @return mixed
	 */
	public function actionTaken($action) {
		return $this->is_Action_ed();
	}

	/**
	 * Return if an action of this actions code exists between the current member and the model.
	 *
	 * @return bool
	 */
	public function is_Action_ed() {
		return parent::checkRelationship(self::ActionCode);
	}

	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $action
	 *
	 * @return SocialModelInterface|null
	 */
	public function provideModel($modelClass, $id, $action) {
		if ($id && ($action === $this->action())) {
			return DataObject::get($modelClass)->byID($id);
		}
	}

	/**
	 * Handle the _action_ request
	 */
	public function settings(SS_HTTPRequest $request) {
		$model = $this()->getModelInstance(self::ActionName);

		// need to this as extend takes a reference
		$action = self::ActionName;

		if ($request->isPOST()) {
			$responses = $this()->extend('afterEdit', $request, $model, $action);
		} else {
			$responses = $this()->extend('beforeEdit', $request, $model, $action);
		}
		// return the first non-falsish response, I don't think we can order them so may as well be first?
		return array_reduce(
			$responses,
			function ($prev, $item) {
				return $prev ?: $item;
			}
		);
	}

	/**
	 * Called on GET to show the model form via renderTemplates.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $model
	 * @param string         $action
	 * @return mixed
	 */
	public function beforeEdit(SS_HTTPRequest $request, DataObject $model, $action) {
		return $this()->renderTemplates($action);
	}

	/**
	 * Called on POST to update the model and write to database.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $model
	 * @param string         $action
	 * @return SS_HTTPResponse
	 */
	public function afterEdit(SS_HTTPRequest $request, DataObject $model, $action) {
		$formName = 'SocialModelForm_' . $this()->getFormName();

		try {
			$member = SocialMember::current_or_guest();

			$selectedRSS = $request->postVar('add_rss'); //note: this var is null if they were on the form, but not ticked

			$member->Interests = $request->postVar('add_interest') ? $request->postVar('add_interest') : $member->Interests;

			$member->isEmailPrivate = (bool) $request->postVar('privacy-email');

			$member->isPhoneNumberPrivate = (bool) $request->postVar('privacy-phone');

			//Break all rss follow relationships
			$relatedRss = $member->RelatedRssFeeds();
			foreach ($relatedRss as $item) {
				if ($rssInstance = RssFeed::get()->byID($item->ToModelID)) {
					MemberRssFeed::remove(
						SocialMember::current_or_guest(),
						$rssInstance,
						"MFR"
					);
				}
			}

			//If any rss feeds were ticked on the form, make the follow relationships
			if ($selectedRSS) {
				foreach ($selectedRSS as $rss) {
					if ($rssInstance = RssFeed::get()->byID($rss)) {
						MemberRssFeed::make(
							SocialMember::current_or_guest(),
							$rssInstance,
							"MFR"
						);
					}
				}
			}

			$member->write();

		} catch (ValidationException $e) {

			SocialForm::set_message($e->getMessage(), 'error');
			return $this()->redirectBack();

		} catch (Exception $e) {
			return $this()->httpError(500, $e->getMessage());
		}

		if ($request->isAjax()) {
			return new SS_HTTPResponse(null, 200);
		} else {
			SocialForm::set_message('PreferencesSavedMessage', SocialForm::Good);
			return $this()->redirectBack();
//			return $this()->redirect($model->ActionLink(Viewable::SocialEdgeType));
		}
	}

	/**
	 *
	 * Member rss Record list for preference form
	 *
	 **/
	public function RssFeedList() {
		$member = SocialMember::current_or_guest();
		$rssFeeds = RssFeed::get();
		$output = new ArrayList();
		foreach ($rssFeeds as $rss) {
			//check if member already has rss checked
			$checked = false;

			if ($memberRssFeeds = $member->RelatedRssFeeds()) {
				$memberFollowsRssFeed = $memberRssFeeds->filter(["ToModelID" => $rss->ID])->first();
				if ($memberFollowsRssFeed) {
					if ($memberFollowsRssFeed->RelationshipType()->Code == "MFR") {
						$checked = true;
					}
				}
			}
			$output->push(ArrayData::create([
				"ID"          => $rss->ID,
				"Title"       => $rss->Title,
				"Description" => $rss->Description,
				"Checked"     => $checked,
				"Logo"        => $rss->Logo(),
			]));
		}

		return $output;
	}

	/**
	 *
	 * Member interests list
	 *
	 **/

	public function InterestList() {
		$member = SocialMember::current_or_guest();
		$memberInterest = $member->Interests;

		return $memberInterest;
	}

	/**
	 *
	 * get member interests json list
	 *
	 * @return JSON
	 *
	 **/
	public function interests_json() {
		$this()->response->addHeader('Content-Type', 'application/json');
		$query = strtolower($this()->request->getVar('term'));

		$allMembers = Member::get();
		$InterestItems = [];
		foreach ($allMembers as $member) {
			if ($member->Interests) {
				$items = explode(",", $member->Interests);
				foreach ($items as $key => $value) {
					if (strtolower(substr($value, 0, strlen($query))) === $query) {
						$InterestItems[] = [
							"id"    => $value,
							"label" => $value,
							"value" => $value,
						];
					}

				}

			}

		}
		$InterestItems = array_slice($InterestItems, 0, 10);
		return json_encode($InterestItems);
	}

}
