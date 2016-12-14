<?php
namespace Modular\Actions;

/**
 * Replyable extends SocialModels to provide a reply and unreply action.
 *
 * @url /$Model/$ID/reply
 * @url /$Model/$ID/unreply
 */

use \Modular\Extensions\Controller\SocialAction;

class Replyable extends SocialAction {
    const ActionCode = 'REP';
    // const Action = 'reply';
    const ReverseMode = 'unreply';

    private static $url_handlers = [
        '$ID/reply' => 'reply',           // ID is the Post's
        '$ID/unreply' => 'unreply'        // ID is the PostReply's being 'unreplied'
    ];
    private static $allowed_actions = [
        'reply' => '->canReply("action")',
        'unreply' => '->canUnReply("action")'
    ];

    /**
     * Return if member has permissions to REP and if any previous required action has happened.
     * @return bool|int
     */
    public function canReply($source = null) {
        return parent::canDoIt(static::ActionCode, $source);
    }

    /**
     * Check if member created the reply via Member-Create-Reply relationship check.
     * @return bool
     */
    public function canUnReply($source = null) {
        // model should be a PostReply
        return (bool)SocialRelationship::has_related(
            Member::currentUser(),
            $this()->getModel(static::ReverseMode),
            'MCR'
        );
    }

    /**
     * If can reply then return a form for PostReply with hidden PostID and a 'reply' action.
     * @return mixed
     */
    public function ReplyableForm() {
        if ($this->canReply()) {
            $form = singleton('PostReply')->formForModel('reply');
            $form->Fields()->push(
                HiddenField::create('PostID', '', $this()->getModelID())
            );
            $form->Actions()->push(
                FormAction::create('reply', 'Post Reply')
            );
            return $form;
        }
    }


    /**
     * @param $model
     * @param $actions
     * @param $mode
     */
    public function updateActionsForMode($model, $actions, $mode) {
        if ($mode == $this->action()) {
            if ($this->canCreate()) {
                $actions->push(new FormAction('Save', 'Save'));
            }
        }
    }



    /**
     * Return true if the action has been taken (e.g. a 'follow' action), false if not or null if not the action that
     * this extension implements.
     *
     * @param $action
     * @return mixed
     */
    public function actionTaken($action)
    {
        return $this->isReplied();
    }

    /**
     * Return if an action of this actions code exists between the current member and the model.
     * @return bool
     */
    public function isReplied() {
        return parent::checkRelationship(self::ActionCode);
    }

    /**
     *
     * The model on the URL is a Post if reply or PostReply if unreply.
     *
     * @param $modelClass
     * @param $id
     * @param $mode
     *
     * @return SocialModelInterface|null
     */
    public function provideModel($modelClass, $id, $mode)
    {
        if ($id) {
            if ($mode === $this->action()) {
                return Post::get()->byID($id);
            } elseif ($mode === static::ReverseMode) {
                return PostReply::get()->byID($id);
            }
        }
    }

    /**
     * Creates a PostReply from the request and associates it with Post via PostReplies relationship.
     * Adds Member-Create-Reply from Member to PostReply
     * Adds Member-Reply-Post from Member to Post
     *
     * @param SS_HTTPRequest $request
     * @return /SS_HttpResponse
     */
    public function reply(SS_HTTPRequest $request) {
        // the model is the Post being replied to.
        /** @var Post $post */
        if ($post = $this()->getModelInstance($this->action())) {
            $reply = new PostReply(
                $request->postVars()
            );
            $reply->write();
            $post->PostReplies()->add($reply);

            // create Member-Create-Reply from member to reply
            SocialRelationship::make(
                Member::currentUser(),
                $reply,
                'MCR'
            );
            // create Member-Replied-Post from member to post
            SocialRelationship::make(
                Member::currentUser(),
                $post,
                'MRP'
            );
        }
        return $this()->redirectBack();
    }

    /**
     * Removes Member-Replied-Post relationship from Member to Post and removes reply
     * from Post's PostReplies relationship.
     *
     * Leaves Post and Member-Create-Reply in place.
     *
     * @param SS_HTTPRequest $request
     */
    public function unreply(SS_HTTPRequest $request) {
        // the model is the PostReply being unreplied
        /** @var PostReply $reply */
        if ($reply = $this()->getModelInstance(static::ReverseMode)) {

            SocialRelationship::remove(
                Member::currentUser(),
                $reply->Post(),
                'MRP'
            );

            $reply->Post()->PostReplies()->remove($reply);

        }
        return $this()->redirectBack();
    }
}
