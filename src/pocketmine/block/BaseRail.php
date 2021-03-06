<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\Player;

abstract class BaseRail extends Flowable{

	public const STRAIGHT_NORTH_SOUTH = 0;
	public const STRAIGHT_EAST_WEST = 1;
	public const ASCENDING_EAST = 2;
	public const ASCENDING_WEST = 3;
	public const ASCENDING_NORTH = 4;
	public const ASCENDING_SOUTH = 5;

	private const ASCENDING_SIDES = [
		self::ASCENDING_NORTH => Facing::NORTH,
		self::ASCENDING_EAST => Facing::EAST,
		self::ASCENDING_SOUTH => Facing::SOUTH,
		self::ASCENDING_WEST => Facing::WEST
	];

	protected const FLAG_ASCEND = 1 << 24; //used to indicate direction-up

	protected const CONNECTIONS = [
		//straights
		self::STRAIGHT_NORTH_SOUTH => [
			Facing::NORTH,
			Facing::SOUTH
		],
		self::STRAIGHT_EAST_WEST => [
			Facing::EAST,
			Facing::WEST
		],

		//ascending
		self::ASCENDING_EAST => [
			Facing::WEST,
			Facing::EAST | self::FLAG_ASCEND
		],
		self::ASCENDING_WEST => [
			Facing::EAST,
			Facing::WEST | self::FLAG_ASCEND
		],
		self::ASCENDING_NORTH => [
			Facing::SOUTH,
			Facing::NORTH | self::FLAG_ASCEND
		],
		self::ASCENDING_SOUTH => [
			Facing::NORTH,
			Facing::SOUTH | self::FLAG_ASCEND
		]
	];

	public function __construct(int $meta = 0){
		$this->meta = $meta;
	}

	public function getHardness() : float{
		return 0.7;
	}

	public function place(Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, Player $player = null) : bool{
		if(!$blockReplace->getSide(Facing::DOWN)->isTransparent() and parent::place($item, $blockReplace, $blockClicked, $face, $clickVector, $player)){
			$this->tryReconnect();
			return true;
		}

		return false;
	}

	protected static function searchState(array $connections, array $lookup) : int{
		$meta = array_search($connections, $lookup, true);
		if($meta === false){
			$meta = array_search(array_reverse($connections), $lookup, true);
		}
		if($meta === false){
			throw new \InvalidArgumentException("No meta value matches connections " . implode(", ", array_map('dechex', $connections)));
		}

		return $meta;
	}

	/**
	 * Returns a meta value for the rail with the given connections.
	 *
	 * @param array $connections
	 *
	 * @return int
	 *
	 * @throws \InvalidArgumentException if no state matches the given connections
	 */
	protected function getMetaForState(array $connections) : int{
		return self::searchState($connections, self::CONNECTIONS);
	}

	/**
	 * Returns the connection directions of this rail (depending on the current block state)
	 *
	 * @return int[]
	 */
	abstract protected function getConnectionsForState() : array;

	/**
	 * Returns all the directions this rail is already connected in.
	 *
	 * @return int[]
	 */
	private function getConnectedDirections() : array{
		/** @var int[] $connections */
		$connections = [];

		/** @var int $connection */
		foreach($this->getConnectionsForState() as $connection){
			$other = $this->getSide($connection & ~self::FLAG_ASCEND);
			$otherConnection = Facing::opposite($connection & ~self::FLAG_ASCEND);

			if(($connection & self::FLAG_ASCEND) !== 0){
				$other = $other->getSide(Facing::UP);

			}elseif(!($other instanceof BaseRail)){ //check for rail sloping up to meet this one
				$other = $other->getSide(Facing::DOWN);
				$otherConnection |= self::FLAG_ASCEND;
			}

			if(
				$other instanceof BaseRail and
				in_array($otherConnection, $other->getConnectionsForState(), true)
			){
				$connections[] = $connection;
			}
		}

		return $connections;
	}

	private function getPossibleConnectionDirections(array $constraints) : array{
		switch(count($constraints)){
			case 0:
				//No constraints, can connect in any direction
				$possible = [
					Facing::NORTH => true,
					Facing::SOUTH => true,
					Facing::WEST => true,
					Facing::EAST => true
				];
				foreach($possible as $p => $_){
					$possible[$p | self::FLAG_ASCEND] = true;
				}

				return $possible;
			case 1:
				return $this->getPossibleConnectionDirectionsOneConstraint(array_shift($constraints));
			case 2:
				return [];
			default:
				throw new \InvalidArgumentException("Expected at most 2 constraints, got " . count($constraints));
		}
	}

	protected function getPossibleConnectionDirectionsOneConstraint(int $constraint) : array{
		$opposite = Facing::opposite($constraint & ~self::FLAG_ASCEND);

		$possible = [$opposite => true];

		if(($constraint & self::FLAG_ASCEND) === 0){
			//We can slope the other way if this connection isn't already a slope
			$possible[$opposite | self::FLAG_ASCEND] = true;
		}

		return $possible;
	}

	private function tryReconnect() : void{
		$thisConnections = $this->getConnectedDirections();
		$changed = false;

		do{
			$possible = $this->getPossibleConnectionDirections($thisConnections);
			$continue = false;

			foreach($possible as $thisSide => $_){
				$otherSide = Facing::opposite($thisSide & ~self::FLAG_ASCEND);

				$other = $this->getSide($thisSide & ~self::FLAG_ASCEND);

				if(($thisSide & self::FLAG_ASCEND) !== 0){
					$other = $other->getSide(Facing::UP);

				}elseif(!($other instanceof BaseRail)){ //check if other rails can slope up to meet this one
					$other = $other->getSide(Facing::DOWN);
					$otherSide |= self::FLAG_ASCEND;
				}

				if(!($other instanceof BaseRail) or count($otherConnections = $other->getConnectedDirections()) >= 2){
					//we can only connect to a rail that has less than 2 connections
					continue;
				}

				$otherPossible = $other->getPossibleConnectionDirections($otherConnections);

				if(isset($otherPossible[$otherSide])){
					$otherConnections[] = $otherSide;
					$other->updateState($otherConnections);

					$changed = true;
					$thisConnections[] = $thisSide;
					$continue = count($thisConnections) < 2;

					break; //force recomputing possible directions, since this connection could invalidate others
				}
			}
		}while($continue);

		if($changed){
			$this->updateState($thisConnections);
		}
	}

	private function updateState(array $connections) : void{
		if(count($connections) === 1){
			$connections[] = Facing::opposite($connections[0] & ~self::FLAG_ASCEND);
		}elseif(count($connections) !== 2){
			throw new \InvalidArgumentException("Expected exactly 2 connections, got " . count($connections));
		}

		$this->meta = $this->getMetaForState($connections);
		$this->level->setBlock($this, $this, false, false); //avoid recursion
	}

	public function onNearbyBlockChange() : void{
		if($this->getSide(Facing::DOWN)->isTransparent() or (
			isset(self::ASCENDING_SIDES[$this->meta & 0x07]) and
			$this->getSide(self::ASCENDING_SIDES[$this->meta & 0x07])->isTransparent()
		)){
			$this->getLevel()->useBreakOn($this);
		}
	}

	public function getVariantBitmask() : int{
		return 0;
	}
}
