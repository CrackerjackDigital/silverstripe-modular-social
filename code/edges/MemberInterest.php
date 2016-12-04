<?php
namespace Modular\Edges;

/**
 * ActionType between a Member and an InterestType.
 */
class MemberInterest extends SocialEdge {
	const FromModelClass = 'Member';
	const ToModelClass   = 'InterestType';

}