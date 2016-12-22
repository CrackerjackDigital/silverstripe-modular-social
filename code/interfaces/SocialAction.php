<?php
namespace Modular\Interfaces;
use Modular\Interfaces\Graph\EdgeType;

/**
 * Interface for Actions which can be performed on a model.
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
     * @return EdgeType
     */
    public function getAction();

    /**
     * Return the top-level 'Parent' action or this action if it is the top level.
     * @return EdgeType
     */
    public function getRootAction();
}