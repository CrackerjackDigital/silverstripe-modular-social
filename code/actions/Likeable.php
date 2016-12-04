<?php

/**
 * Extension adds actions like and unlike which create relationship between viewed object and logged in member.
 *
 * Provides LikeableLink method to link to this objects like/unlike action.
 */
namespace Modular\Actions;

use Modular\Extensions\Controller\SocialController;
use \Modular\Extensions\Controller\SocialAction;

class Likeable extends SocialAction {
    const ActionTypeCode = 'LIK';
    const Action = 'like';

    private static $url_handlers = [
        '$ID/like' => 'like',
        '$ID/unlike' => 'unlike'
    ];

    private static $allowed_actions = [
        'like' => '->canLike("action")',
        'unlike' => '->canLike("action")'
    ];


    /**
     * Return boolean to indicate if the action for this extension has been taken, or null if not the action
     * that this extension deals with.
     *
     * @param $action
     * @return bool|mixed
     */
    public function actionTaken($action) {
        if ($action === self::ActionTypeCode) {
            return self::isLiked();
        }
    }
    /**
     * Checks the user can like the extended model.
     *
     * TODO: should also filter out e.g. Posts which the user has made themselves.
     *
     * @return bool
     */
    public function canLike($source = null) {
        return parent::canDoIt(self::ActionTypeCode, $source);
    }

    /**
     * Member likes this->owner object, add a relationship from Member with type self::$actionTypeCode
     */
    public function like() {
        parent::makeRelationship(self::ActionTypeCode);
        return Controller::curr()->redirectBack();
    }

    /**
     * Member unlikes this->owner object, remove all self::$actionTypeCode relationships between them
     * @param null $mmeberID
     */
    public function unlike() {
        parent::breakRelationship(self::ActionTypeCode);
        return Controller::curr()->redirectBack();
    }

    public function isLiked() {
        return parent::checkRelationship(self::ActionTypeCode);
    }
    /**
     * Return a link appropriate for this object to be likeed by logged in Member if can be likeed.
     * @param $action
     * @return String
     */
    public function LikeableLink($action) {
        if ($this->canLike()) {
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
        if ($mode === static::Action) {
            if ($id) {
                return DataObject::get($modelClass)->byID($id);
            }
        }
    }
}