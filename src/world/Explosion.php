<?php

/**
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

class Explosion{
	private $rays = 16; //Rays
	public $level;
	public $source;
	public $size;
	public $affectedBlocks = array();
	public $stepLen = 0.3;
	
	public function __construct(Position $center, $size){
		$this->level = $center->level;
		$this->source = $center;
		$this->size = max($size, 0);
	}
	
	public function explode(){
		$server = ServerAPI::request();
		if($this->size < 0.1 or $server->api->dhandle("entity.explosion", array(
			"level" => $this->level,
			"source" => $this->source,
			"size" => $this->size
		))){
			return false;
		}

		$mRays = $this->rays - 1;
		for($i = 0; $i < $this->rays; ++$i){
			for($j = 0; $j < $this->rays; ++$j){
				for($k = 0; $k < $this->rays; ++$k){
					if($i == 0 or $i == $mRays or $j == 0 or $j == $mRays or $k == 0 or $k == $mRays){
						$vector = new Vector3($i / $mRays * 2 - 1, $j / $mRays * 2 - 1, $k / $mRays * 2 - 1); //($i / $mRays) * 2 - 1
						$vector = $vector->normalize()->multiply($this->stepLen);
						$pointer = clone $this->source;
						
						for($blastForce = $this->size * (mt_rand(700, 1300) / 1000); $blastForce > 0; $blastForce -= $this->stepLen * 0.75){
							$vBlock = $pointer->floor();
							if($vBlock->y >= 128 or $vBlock->y < 0){
								break;
							}
							$block = $this->level->getBlockRaw($vBlock);
			
							if(!($block instanceof AirBlock)){
								$blastForce -= ($block->getHardness() / 5 + 0.3) * $this->stepLen;
								if($blastForce > 0){
									$index = ($vBlock->x << 15) + ($vBlock->z << 7) +  $vBlock->y;
									if(!isset($this->affectedBlocks[$index])){
										$this->affectedBlocks[$index] = $block;
									}
								}
							}
							$pointer = $pointer->add($vector);
						}
					}
				}
			}
		}
		
		$send = array();
		$airblock = new AirBlock();
		$source = $this->source->floor();
		$radius = 2 * $this->size;
		foreach($server->api->entity->getRadius($this->source, $radius) as $entity){
			$impact = (1 - $this->source->distance($entity) / $radius) * 0.5; //placeholder, 0.7 should be exposure
			$damage = (int) (($impact * $impact + $impact) * 8 * $this->size + 1);
			$entity->harm($damage, "explosion");
		}

		foreach($this->affectedBlocks as $block){
			$this->level->setBlockRaw($block, $airblock, false, false); //Do not send record
			if($block instanceof TNTBlock){
				$data = array(
					"x" => $block->x + 0.5,
					"y" => $block->y + 0.5,
					"z" => $block->z + 0.5,
					"power" => 4,
					"fuse" => mt_rand(10, 30), //0.5 to 1.5 seconds
				);
				$e = $server->api->entity->add($this->level, ENTITY_OBJECT, OBJECT_PRIMEDTNT, $data);
				$server->api->entity->spawnToAll($e);
			}
			$send[] = new Vector3($block->x - $source->x, $block->y - $source->y, $block->z - $source->z);
			if(mt_rand(0, 10000) < ((1/$this->size) * 10000)){
				$server->api->entity->drop(new Position($block->x + 0.5, $block->y, $block->z + 0.5, $this->level), BlockAPI::getItem($block->getID(), $block->getMetadata()));
			}
		}
		$server->api->player->broadcastPacket($server->api->player->getAll($this->level), MC_EXPLOSION, array(
			"x" => $this->source->x,
			"y" => $this->source->y,
			"z" => $this->source->z,
			"radius" => $this->size,
			"records" => $send,
		));

	}
}