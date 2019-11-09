<?php

/*
このファイルはmaa123氏のコードを一部改造したものです。
*/

namespace zerosan96\music;

use zerosan96\mysql\MySQL;
use pocketmine\Player;
use pocketmine\Entitiy;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\SetEntityDataPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\ClientboundMapItemDataPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\utils\UUID;
use pocketmine\utils\Internet;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\Task;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageByEntityEvent; 
use pocketmine\event\entity\EntityDamageEvent; 
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDeathEvent;
use zerosan96\CallbackTask;
use zerosan96\BossBar;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\entity\EffectInstance;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\ArmorInventoryEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\event\entity\EntityInventoryChangeEvent;
use pocketmine\event\entity\EntityArmorChangeEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\utils\Color;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\math\AxisAlignedBB;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\network\mcpe\protocol\MoveEntityAbsolutePacket;
use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\entity\projectile\Allow;
use pocketmine\event\entity\EntityDamageByChildEntityEvent; 
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\byteTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use zerosan96\event\StatusEvent;
use zerosan96\game\NowPlay;
use zerosan96\game\Team;
use zerosan96\game\Game;
use zerosan96\item\ItemEffect;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;

class Music implements Listener{

	public function __construct(PluginBase $plugin){

		$this->plugin = $plugin;
		$this->SoundServer[$this->taskNumber] = null;
		$this->nowSound = null;

	}

	public function getSoundList(){
		$_list = [];
		foreach ($this->plugin->soundList as $key => $sound) {
			$_list[] = [$key, basename($sound, '.json')];
		}
		return $_list;
	}

	public function reloadSoundList(){
		$this->plugin->soundList = glob($this->plugin->getDataFolder()."music/*.json");
	}

	public function stopSound(){
		if(isset($this->SoundServer[$this->taskNumber])){
			$this->SoundServer[$this->taskNumber]->getHandler()->cancel();
			$this->SoundServer[$this->taskNumber] = null;
		}
	}

	public function startSoundNumber(int $num){
		if(!isset($this->plugin->soundList[$num])){
			return false;
		}
		$_midi = json_decode(file_get_contents($this->plugin->soundList[$num]), true);
		$musicName = basename($this->plugin->soundList[$num], ".json");
		if($musicName === "ただ君に晴れ"){
			$this->startSound($_midi['data'], 3980, 20);
		}else{
			$this->startSound($_midi['data'], ceil($_midi['maxtick']), 20);
		}
		$this->nowSound = $num;
		return true;
	}

	private function startSound($notes, $time, $delay = 200){
		if(!is_null($this->SoundServer[$this->taskNumber])){
			$this->SoundServer[$this->taskNumber]->getHandler()->cancel();
			$this->SoundServer[$this->taskNumber] = null;
		}
		$this->plugin->getScheduler()->scheduleDelayedRepeatingTask(new class($this, $notes, $time) extends Task{
			private $_tick = 0;
			protected static $progms = ['note.harp', 'note.bassattack', 'note.bd', 'note.snare', 'note.hat', 'note.pling'];
			public function __construct($_this, $notes, $time){
				$this->_this = $_this;
				$this->sound = $notes;
				$_this->SoundServer[$this->_this->taskNumber] = $this;
				$this->time = $time;
				$this->endTime = self::SecToMinSec($time/20);
			}
			public static function SecToMinSec($sec){
				$min = floor($sec / 60);
				$sec = floor($sec % 60);
				return ($min>0)?($min."分".$sec."秒"):($sec."秒");
			}
			public function onRun(int $tick){
				if($this->time < $this->_tick){
					$this->getHandler()->cancel();
					$this->_this->SoundServer[$this->_this->taskNumber] = null;
					if(!$this->_this->startSoundNumber($this->_this->nowSound + 1)){
						$this->_this->startSoundNumber(0);
						$musicName = basename($this->_this->plugin->soundList[0], ".json");
						$this->_this->plugin->getLogger()->info("§a>> 次の曲を再生します。 曲名: {$musicName}");
						foreach($this->_this->plugin->getServer()->getLevelByName("lobby")->getPlayers() as $player){
							$player->sendMessage("§a>> 次の曲を再生します。 曲名: {$musicName}");
						}
					}else{
						$musicName = basename($this->_this->plugin->soundList[$this->_this->nowSound], ".json");
						$this->_this->plugin->getLogger()->info("§a>> 次の曲を再生します。 曲名: {$musicName}");
						foreach($this->_this->plugin->getServer()->getLevelByName("lobby")->getPlayers() as $player){
							$player->sendMessage("§a>> 次の曲を再生します。 曲名: {$musicName}");
						}
					}
					return;
				}
				//$this->_this->plugin->getServer()->broadcastTip("§a[音楽] ".self::SecToMinSec($this->_tick/20).'/'.$this->endTime);
				if(isset($this->sound[$this->_tick])){
					foreach ($this->sound[$this->_tick] as $note){
						foreach($this->_this->plugin->getServer()->getLevelByName("lobby")->getPlayers() as $player){
							$pk = new PlaySoundPacket();
							if(!isset($note[2])){
								$pk->soundName = "note.harp";
							}else{
								$pk->soundName = self::$progms[$note[2]];
							}
							$pk->pitch = $note[0];
							$pk->volume = $note[1];
							$pk->x = $player->x;
							$pk->y = $player->y + 5;
							$pk->z = $player->z;
							$player->dataPacket($pk);
						}
					}
				}
				$this->_tick++;
			}
		}, $delay, 1);
	}

}
