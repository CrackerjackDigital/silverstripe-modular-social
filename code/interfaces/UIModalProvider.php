<?php
namespace Modular\Interfaces;

use Modular\Forms\SocialForm;

interface UIModalProvider {
    /**
     * Return the content of a modal dialog depending on mode which should match the
     * extensions mode.
     *
     * @param $mode
     * @return SocialForm
     */
    public function provideUIModal(\SS_HTTPRequest $request);
}