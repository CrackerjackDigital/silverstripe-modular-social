<?php
namespace Modular\UI\Dialogs;

use Modular\Extensions\Controller\SocialController;
use SS_HTTPRequest;

/**
 * Responds to a request to show a modal dialog with the content of a modal dialog as pjax.
 */
class SocialModalProvider extends SocialController  {
    const ActionName = 'uimodal';

    private static $url_handlers = [
        '$ID!/$Mode!/uimodal' => 'uimodal'
    ];
    private static $allowed_actions = [
        self::ActionName => '->canShowUIModal'
    ];

    /**
     * TODO: harden? The actual submit should fail so allow to show anyway?
     * @return bool
     */
    public function canShowUIModal() {
        return true;
    }

	/**
	 * Returns a form for the current model suitable for inserting into the ModalContainer.
	 *
	 * Given an endpoint '/organisation/10/join/modal' should provide a form with fields necessary
	 *
	 * @param SS_HTTPRequest $request
	 * @return mixed
	 */
    public function uiModal(SS_HTTPRequest $request) {
        return array_reduce(
            $this()->extend('provideUIModal', $request),
            function($prev, $item) {
                return $prev ?: $item;
            }
        );
    }

}