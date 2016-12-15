<?php
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;
use SS_HTTPRequest;
use SS_HTTPResponse_Exception;

class Deleteable extends SocialAction  {
    const ActionCode = 'DEL';
    const ActionName = 'delete';

    private static $url_handlers = [
        '$ID/delete' => self::ActionName
    ];

    private static $allowed_actions = [
        self::ActionName => '->canDelete("action")'
    ];

    private static $action_templates = [
	    self::ActionName => self::ActionName,
    ];

    private static $action_modes = [
	    self::ActionName => self::ActionName,
    ];


    /**
     * Check member can do the 'CRT' action as it is also delete action.
     * @param null $member
     * @return bool|int|void
     */
    public function canDelete($source = null) {
        return parent::canDoIt(self::ActionCode, $source);
    }

    /**
     * Return the Model form configured for 'delete' action.
     * @return Form
     */
    public function DeleteForm() {
        return $this()->formForModel(self::ActionName);
    }

    /**
     * Handles the 'delete' action, only POST. If GET then returns 405.
     */
    public function delete(SS_HTTPRequest $request) {
        if ($request->httpMethod() !== 'POST') {
            return new SS_HTTPResponse_Exception('', 405);
        }
        $response = $this()->process($request);
        return $this()->afterDelete($request, $response);
    }
}