<?php
namespace Modular\Interfaces;

/**
 * Interface SocialModelInterface
 */
interface SocialModel {
	/**
	 * @return string
	 */
	public function getModelClass();

	/**
	 * @return int|null
	 */
	public function getModelID();

	/**
	 * @return SocialModel|null
	 */
	public function getModelInstance();
    /**
     * Return the endpoint (url path) for talking with this model without leading
     * or trailing slashes.
     *
	 * @return string endpoint e.g. 'notification' for 'NotificationModel'
     */
    public function endpoint();
}