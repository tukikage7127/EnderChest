<?php

namespace EC;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\tile\Tile;
use pocketmine\tile\Chest;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\inventory\ChestInventory;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\block\BlockBreakEvent;

class EC extends PluginBase implements Listener{

    function onEnable()
    {
    	$this->getServer()->getPluginManager()->registerEvents($this, $this);
    	if(!file_exists($this->getDataFolder())) mkdir($this->getDataFolder(), 0744, true);
    	$this->C = new Config($this->getDataFolder()."Chests.yml",Config::YAML,["Datas" => []]);
    	$this->C->save();
    	$this->use = [];
    	$this->Chests = $this->C->get("Datas");
    }

    function onDisable()
	{
    	if (isset($this->Chests)) {
			$this->C->setAll(["Datas" => $this->Chests]);
			$this->C->save();
		}
	}

	function onJoin(PlayerJoinEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		if (!isset($this->Chests[$name])) $this->Chests[$name] = [];
	}

	function onQuit(PlayerQuitEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		if (isset($this->data[$name])) {
			unset($this->use[$this->data[$name]]);
			$this->data[$name] = null;
		}
	}

	function onBreak(BlockBreakEvent $event)
	{
		if ($event->isCancelled()) return;
		$block = $event->getBlock();
		$level = $block->level;
		$data = $data = implode(":",[$level->getName(),$block->x,$block->y,$block->z]);
		if (isset($this->use[$data])) {
			$event->setCancelled();
			$event->getPlayer()->sendMessage("§c[EC] ".$this->use[$data]."様がエンダーチェストを使用しています");
		}
	}

	function onTap(PlayerInteractEvent $event)
	{
		if ($event->isCancelled()) return false;
		$player = $event->getPlayer();
		if ($player->isCreative() or $player->isSpectator()) return false;
		$name = $player->getName();
		$item = $event->getItem();
		$block = $event->getBlock();
		if ($block->getId() === 146) {
			if ($event->getAction() != 1) return false;
			$level = $player->level;
			$chest = $level->getTile($block);
			$inventory = $chest->getInventory();
			$data = implode(":",[$level->getName(),$block->x,$block->y,$block->z]);
			if ($inventory instanceof ChestInventory and !$inventory instanceof DoubleChestInventory) {
				if ($chest instanceof Chest) {
					if (!isset($this->use[$data])) {
						$this->use[$data] = $name;
						$this->data[$name] = $data;
					}else{
						$player->sendMessage("§c[EC] ".$this->use[$data]."様がエンダーチェストを使用しています");
						$event->setCancelled();
					}
				}
			}
		}
	}
	
	function onOpen(InventoryOpenEvent $event)
	{
		if ($event->isCancelled()) return false;
		$player = $event->getPlayer();
		$name = $player->getName();
		$inventory = $event->getInventory();
		if ($inventory instanceof ChestInventory and !$inventory instanceof DoubleChestInventory) {
			$block = $inventory->getHolder();
			$data = implode(":",[$block->level->getName(),$block->x,$block->y,$block->z]);
			if (!isset($this->use[$data])) return false;
			if ($this->use[$data] === $name) {
				foreach ($this->Chests[$name] as $slot => $itemdatas) {
					$itemdata = explode(":",$itemdatas);
					$item = Item::get($itemdata[0],$itemdata[1],$itemdata[2]);
					$inventory->setItem($slot,$item);
				}
			}
			$inventory->sendContents($player);
			$player->sendMessage("§a[EC] エンダーチェストを読み込みました");
		}
	}

	function onClose(InventoryCloseEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		$inventory = $event->getInventory();
		if ($inventory instanceof ChestInventory and !$inventory instanceof DoubleChestInventory) {
			$block = $inventory->getHolder();
			$data = implode(":",[$block->level->getName(),$block->x,$block->y,$block->z]);
			if (!isset($this->use[$data])) return false;
			if ($this->use[$data] === $name) {
				$this->Chests[$name] = [];
				foreach ($inventory->getContents() as $slot => $item) {
					$itemdatas = implode(":",[$item->getId(),$item->getDamage(),$item->getCount()]);
					$this->Chests[$name][$slot] = $itemdatas;
				}
			}
			if (count($event->getViewers()) === 1) $inventory->clearAll();
			unset($this->use[$this->data[$name]]);
			$this->data[$name] = null;
			$player->sendMessage("§a[EC] エンダーチェストを保存しました");
		}
	}
}