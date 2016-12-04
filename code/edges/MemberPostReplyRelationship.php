<?php
namespace Modular\Edges;

/**
 * ActionType between a Member and a PostReply.
 */
class MemberPostReply extends SocialEdge {
	const FromModelClass = 'Member';
	const ToModelClass = 'PostReply';

}