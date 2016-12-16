<?php
namespace Modular\Collections;

use Modular\Edges\Directed;
use Modular\Interfaces\Graph\EdgeType;
use Modular\Models\Graph\Edge;
use Modular\Models\Graph\Node;

/**
 * A DirectedNodeList is a list of graph Nodes or derived models, and so could be at either of an Edge.
 *
 * @package Modular\Collections
 */
class DirectedNodeList extends \DataList {
	const InjectorName = 'GraphNode';
	private static $injector_name = self::InjectorName;

	/**
	 * @return DirectedNodeList
	 */
	public static function create() {
		return \Injector::inst()->createWithArgs(static::config()->get('injector_name') ?: get_called_class(), func_get_args());
	}

	/**
	 * Return a list of all nodes which are related to nodes in this list as the 'To' node.
	 *
	 * @return DirectedNodeList
	 */
	public function to($filter = []) {
		return DirectedEdgeList::create(
			get_class(static::edge())
		)->filter([
			static::from_field_name('ID') => $this->column('ID')
		])->to($filter);
	}

	/**
	 * Return a list of all nodes which are related to nodes in this list as the 'From' node.
	 *
	 * @return DirectedNodeList
	 */
	public function from($filter = []) {
		return DirectedEdgeList::create(
			get_class(static::edge())
		)->filter([
			static::to_field_name('ID') => $this->column('ID')
		])->from($filter);
	}

	/**
	 * @return Directed
	 */
	protected static function edge() {
		static $edge;
		return $edge ?: $edge = Directed::create();
	}

	/**
	 * @param string $suffix
	 * @return string
	 */
	protected static function from_field_name($suffix = 'ID') {
		return static::edge()->node_a_field_name($suffix);
	}

	/**
	 * @param string $suffix
	 * @return string
	 */
	protected static function to_field_name($suffix = 'ID') {
		return static::edge()->node_b_field_name($suffix);
	}

}