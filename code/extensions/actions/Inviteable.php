<?php

/**
 * Extension adds actions invite and uninvite which create relationship between viewed object and logged in member.
 *
 * Provides InviteableLink method to link to this objects invite/uninvite action.
 */
namespace Modular\Actions;

use Controller;
use DataObject;
use Member;
use Modular\Edges\SocialRelationship;
use \Modular\Extensions\Controller\SocialAction;
use Modular\Interfaces\SocialModel;

class Inviteable extends SocialAction {
    const ActionCode = 'IVT';
    const ActionName = 'invite';

    private static $allowed_actions = [
        'invite' => '->canInvite("action")',
        'uninvite' => '->canInvite("action")'
    ];


    /**
     * Return boolean to indicate if the action for this extension has been taken, or null if not the action
     * that this extension deals with.
     *
     * @param $action
     * @return bool|mixed
     */
    public function actionTaken($action) {
        if ($action === self::ActionCode) {
            return self::isInvited();
        }
    }
    /**
     * Permissive for now. TODO: sort out permissions CAN_INVITE_xxx.
     * @return bool
     */
    public function canInvite($source = null) {
        return parent::canDoIt(self::ActionCode, $source);
    }

    /**
     * Member invites this->owner object, add a relationship from Member with type self::$actionTypeCode
     */
    public function invite() {
        parent::make(Member::currentUser(), $this(), self::ActionCode);
        return Controller::curr()->redirectBack();
    }

	/**
	 * Member uninvites this->owner object, remove all self::$actionTypeCode relationships between them
	 *
	 * @return bool|\SS_HTTPResponse
	 */
    public function uninvite() {
        parent::remove(Member::currentUser(), $this(), self::ActionCode);
        return Controller::curr()->redirectBack();
    }

    public function isInvited() {
        return SocialRelationship::latest(Member::currentUser(), $this(), self::ActionCode);
    }
    /**
     * Return a link appropriate for this object to be inviteed by logged in Member if can be inviteed.
     * @param $action
     * @return String
     */
    public function InviteableLink($action) {
        if ($this->canInvite()) {
            return Controller::curr()->join_links(
                $this()->Link(),
                $action
            );
        }
    }

    /**
     * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
     * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
     * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
     *
     * @param DataObject|string $modelClass
     * @param $id
     * @param $action
     *
     * @return SocialModel|null
     */
    public function provideModel($modelClass, $id, $action)
    {
        if ($action === $this->action()) {
	        $modelClass = static::derive_class_name($modelClass);
            if ($id) {
                return $modelClass::get()->byID($id);
            }
        }
    }

}