<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;

class AnvilInventory extends ContainerInventory implements FakeContainer{

	/** @var Position */
	protected $holder;

	public function __construct(Position $pos){
		parent::__construct($pos->asPosition());
	}

	public function getNetworkType() : int{
		return WindowTypes::ANVIL;
	}

	public function getName() : string{
		return "Anvil";
	}

	public function getDefaultSize() : int{
		return 3; //1 input, 1 material, 1 result
	}

	/**
	 * This override is here for documentation and code completion purposes only.
	 * @return Position
	 */
	public function getHolder(){
		return $this->holder;
	}

	public function onClose(Player $who) : void{
		parent::onClose($who);

		$this->dropContents($this->holder->getLevel(), $this->holder->add(0.5, 0.5, 0.5));
	}
}
