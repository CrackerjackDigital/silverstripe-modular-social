<?php
namespace Modular\Interfaces;

/**
 * Interface SocialModelInterface
 */
interface SocialModel {
	/**
	 * @return string
	 */
	public function modelClassName();

	/**
	 * @return int|null
	 */
	public function modelID();

	/**
	 * @return SocialModel|null
	 */
	public function model();
    /**
     * Return the endpoint (url path) for talking with this model without leading
     * or trailing slashes.
     *
	 * @return string endpoint e.g. 'notification' for 'NotificationModel'
     */
    public function endpoint();
}