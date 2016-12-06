<?php

/**
 * Extension which can report on a model's 'NewsFeed', that is other models which have a LIK or FOL relationship
 */
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;

class NewsFeed extends SocialAction  {
	const ActionTypeCode = 'VEW';
	const Action           = 'newsfeed';

	private static $template_name = self::Action;

	private static $newsfeed_type_limit = 0;

	private static $url_handlers = [
		'index'        => 'index',
		self::Action   => 'index',
		'$ID/newsfeed' => 'index',
	];

	private static $allowed_actions = [
		'index' => '->canViewNewsFeed("action")',
	];

	private static $action_templates = [
		'index'      => self::Action,
		self::Action => self::Action,
	];

	/**
	 * For type-hint only.
	 *
	 * @return SocialModel_Controller
	 */
	public function __invoke() {
		return parent::__invoke();
	}

	public function canViewNewsFeed($source = null) {
		if (!$member = Member::currentUser()) {
			$this()->httpError(403);
		}
		if (!$model = $this()->getModelInstance($mode = self::Action)) {
			$this()->httpError(404);
		}
		return parent::canDoIt(self::ActionTypeCode, $source);
	}

	/**
	 * Handle the index and newsfeed actions on a controller
	 * maybe without having an ID on the URL as we can use the logged in Member (or guest member).
	 *
	 * @param SS_HTTPRequest $request
	 * @return HTMLText
	 */
	public function index(SS_HTTPRequest $request) {
		return $this()->renderTemplates(Config::inst()->get(__CLASS__, 'template_name'));
	}

	/**
	 * Returns a list o
	 *
	 * // TODO this breaks the pattern, should be NewsFeed or all template data methods update to NewsFeedData.
	 */
	public function NewsFeed($actionTypeCodes = 'LIK,FOL,CRT,POS,MCT,MRO,MCO,MFO') {
		$listItems = new PaginatedList(
			$this->newsFeedList($actionTypeCodes)
		);
		$request = $this()->getRequest();

		$listItems->setPageLength(20);
		$listItems->setPageStart($request->getVar('start'));

		$data = [
			'Title'    => _t('HasNewsFeed.WidgetTitle', 'News feed', 'News feed'),
			'Content'  => _t('HasNewsFeed.WidgetContent', 'Here are your news feed items:'),
			'Model'    => $this(),
			'ListView' => [
				'ExtraClasses' => 'endless',
				'ListItems'    => $listItems,
			],
		];
		return new ArrayData($data);
	}

	/**
	 * Returns array of tabs for the tab strip on newsfeed page
	 *
	 * @return ArrayList
	 */
	public function NavTabBar() {
		$tabs = new ArrayList([
			[
				'ID'    => 'feedTab',
				'Title' => _t('NewsFeed.NewsFeedTabLabel', 'News Feed'),
			],
			[
				'ID'    => 'organisationTab',
				'Title' => _t('NewsFeed.MyOrganisationTabLabel', 'My SocialOrganisation Profile'),
			],
			[
				'ID'    => 'myPersonalProfileTab',
				'Title' => _t('NewsFeed.MyPersonalProfileTabLabel', 'My Personal Profile'),
			],
		]);

		return $tabs;
	}

	/**
	 * Returns SS_List of:
	 *
	 * - last 5 recently registered organisations
	 * - Posts which the member has a CRT relationship to
	 * - Posts which are made by people the Member follows
	 * - Posts which are made by organisations the Member follows
	 *
	 *
	 * @param $actionTypeCodes
	 * @return DataList|Modular\Collections\RoundRobinMultipleArrayList
	 */
	private function newsFeedList($actionTypeCodes) {
		$mode = self::Action;
		$filters = $this()->request->getVar('filter');
		/**
		 *
		 * TODO:
		 * - Apply filter to news feeds
		 **/

		if (!$model = $this()->getModelInstance(self::Action)) {
			return $this()->redirect('/Security/login');
		}
		// TODO make this per item type for each model type returned in list
		$limit = (int) static::get_config_setting('newsfeed_type_limit');

		// ask model extensions to provide one or more lists/queries of items for the list
		$sources = $model->extend('provideListItemsForAction', $mode, $actionTypeCodes);

		$lists = [];
		$feedLists = [];

		foreach ($sources as $source) {
			if (!is_array($source)) {
				$source = [$source];
			}
			/** @var DataList $query */
			foreach ($source as $query) {
				$query = $query->limit($limit)->sort('Created', 'Desc');
				if ($query->count()) {
					foreach ($query as $item) {
						$feedLists[ $item->Created ] = $item;
					}
				}
				$lists[] = $query;
			}
		}
		krsort($feedLists);

		return new \Modular\Collections\RoundRobinMultipleArrayList($feedLists);
	}

	public function provideModel($modelClass, $id, $mode) {
		if ($mode === static::Action) {
			return Member::currentUser();
		}
	}

	/**
	 * @param $mode
	 * @param $actionTypeCodes
	 * @return mixed
	 */
	public function providListItemsForAction($mode, $actionTypeCodes) {
		if ($mode === NewsFeed::Action) {
			if ($model = $this()->getModelInstance($mode)) {
				return $model->related($actionTypeCodes);
			}
		}
	}

	/**
	 *
	 * Get Active Filter items list
	 *
	 * @return array
	 *
	 **/
	public function GetActiveFilters() {
		$filters = $this()->request->getVar('filter');
		$output = ArrayList::create();

		if ($filters) {
			foreach ($filters as $item) {
				switch ($item) {
				case 'groups':
					$itemName = "My Groups";
					break;

				case 'favourites':
					$itemName = "My Favourites";
					break;

				case 'recommended':
					$itemName = "Recommended";
					break;

				default:
					$itemName = "";
					break;
				}
				$output->push(ArrayData::create(array("filter" => $itemName)));
			}
		}

		return $output;
	}

	/**
	 *
	 * Create filter link
	 *
	 * @return string
	 *
	 **/
	public function CreateFilterLink($Filterlink) {
		$member_id = Member::CurrentUser()->ID;
		$ActiveFilters = $this()->request->getVar('filter');
		$filter = "";
		if ($ActiveFilters) {
			foreach ($ActiveFilters as $filterItem) {
				if ($filterItem != $Filterlink) {
					$filter .= "filter[]=" . $filterItem . "&";
				}
			}
		}

		switch ($Filterlink) {
		case 'groups':
			$filter .= "filter[]=groups";
			break;
		case 'favourites':
			$filter .= "filter[]=favourites";
			break;

		case 'recommended':
			$filter .= "filter[]=recommended";
			break;

		default:
			$filter = "";
			break;
		}
		return "member/" . $member_id . "/newsfeed/?" . $filter;
	}

}