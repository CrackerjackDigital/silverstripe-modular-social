<?php

/**
 * Extension adds actions invite and uninvite which create relationship between viewed object and logged in member.
 *
 * Provides InviteableLink method to link to this objects invite/uninvite action.
 */
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;

class Inviteable extends SocialAction {
    const ActionCode = 'IVT';
    // const Action = 'invite';

    private static $url_handlers = [
        '$ID/invite' => 'invite',
        '$ID/uninvite' => 'uninvite'
    ];

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
        parent::makeRelationship(self::ActionCode);
        return Controller::curr()->redirectBack();
    }

    /**
     * Member uninvites this->owner object, remove all self::$actionTypeCode relationships between them
     * @param null $mmeberID
     */
    public function uninvite() {
        parent::breakRelationship(self::ActionCode);
        return Controller::curr()->redirectBack();
    }

    public function isInvited() {
        return parent::checkRelationship(self::ActionCode);
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
     * @param $modelClass
     * @param $id
     * @param $mode
     *
     * @return SocialModelInterface|null
     */
    public function provideModel($modelClass, $id, $mode)
    {
        if ($mode === $this->action()) {
            if ($id) {
                return DataObject::get($modelClass)->byID($id);
            }
        }
    }

}