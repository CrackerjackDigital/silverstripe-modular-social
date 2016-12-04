<?php
namespace Modular\Interfaces;

interface UIModalProvider {
    /**
     * Return the content of a modal dialog depending on mode which should match the
     * extensions mode.
     *
     * @param $mode
     * @return SocialModelForm
     */
    public function provideUIModal(SS_HTTPRequest $request);
}