<?php
namespace Modular\Interfaces;

/**
 * Interface for CurrentMemberRelationshipExtensions, partly to ensure they implement an actionTaken method which
 * can be used to determine if an action has been done and so the reverse action is 'next'.
 */
interface SocialAction {
    /**
     * Return true if the action has been taken (e.g. a 'follow' action), false if not or null if not the action that
     * this extension implements.
     *
     * @param $action
     * @return mixed
     */
    public function actionTaken($action);

    /**
     * @return SocialModel
     */
    public function getFrom();

    /**
     * @return SocialModel
     */
    public function getTo();

    /**
     * @return RelationshipType
     */
    public function getAction();

    /**
     * Return the top-level 'Parent' action or this action if it is the top level.
     * @return RelationshipType
     */
    public function getRootAction();
}