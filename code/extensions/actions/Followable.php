<?php

/**
 * Extension adds actions follow and unfollow which create relationship between viewed object and logged in member.
 *
 * Provides FollowableLink method to link to this objects follow/unfollow action.
 */
namespace Modular\Actions;

use Controller;
use Member;
use Modular\Edges\SocialRelationship;
use Modular\Extensions\Controller\SocialAction;

class Followable extends SocialAction {
    const ActionCode = 'FOL';
    const ActionName = 'follow';

    private static $url_handlers = [
        '$ID/follow' => 'follow',
        '$ID/unfollow' => 'unfollow'
    ];

    private static $allowed_actions = [
        'follow' => '->canFollow("action")',
        'unfollow' => '->canFollow("action")'
    ];

	/**
	 * Checks if there is a logged in member and a valid RelationshipType exists
	 * between the member and the extended class.
	 *
	 * @param null $source
	 * @return bool
	 */
    public function canFollow($source = null) {
        return parent::canDoIt(self::ActionCode, $source);
    }
    /**
     * Return boolean to indicate if the action for this extension has been taken, or null if not the action
     * that this extension deals with.
     *
     * @param $action
     * @return bool|mixed
     */
    public function actionTaken($action) {
        return ($action === self::ActionCode) && self::isFollowed();
    }

    /**
     * Member follows this->owner object, add a relationship from Member with type self::$ActionCode
     */
    public function follow() {
        parent::make(Member::currentUser(), $this());
        return Controller::curr()->redirectBack();
    }

	/**
	 * Member unfollows this->owner object, remove all self::$ActionCode relationships between them
	 *
	 * @return bool|\SS_HTTPResponse
	 * @internal param null $mmeberID
	 */
    public function unfollow() {
        parent::remove(Member::currentUser(), $this());
        return Controller::curr()->redirectBack();
    }

	/**
	 * Check a relationship exists of type 'FOL' between current member and extended model
	 * @return \DataObject|\Modular\Edges\SocialRelationship
	 */
    public function isFollowed() {
        return SocialRelationship::latest(Member::currentUser(), $this(), self::ActionCode);
    }

    /**
     * Return a link appropriate for this object to be followed by logged in Member if can be followed.
     * @param $action
     * @return String
     */
    public function FollowableLink($action) {
        if ($this->canFollow()) {
            return Controller::curr()->join_links(
                $this()->getModelInstance(self::ActionName)->Link(),
                $action
            );
        }
    }

}