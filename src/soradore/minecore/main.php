<?php

   /**  
    *   __  __ _               ____               
    *  |  \/  (_)_ __   ___   / ___|___  _ __ ___ 
    *  | |\/| | | '_ \ / _ \ | |   / _ \| '__/ _ \
    *  | |  | | | | | |  __/ | |__| (_) | | |  __/
    *  |_|  |_|_|_| |_|\___|  \____\___/|_|  \___|
    *
    *
    *   @author soradore
    *
    *   ################# Rules ##################
    *   #                                        #
    *   #                                        #
    *   #                                        #
    *   ##########################################
    **/                                         

namespace soradore\minecore;


/* Base */
use pocketmine\plugin\PluginBase;

/* Events */
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\QuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;


/* Save */
use pocketmine\utils\Config;

/* Item and Block */
use pocketmine\item\Item;
use pocketmine\block\Block;

/* Level and Math */
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class main extends PluginBase implements Listener{

    //@var array  $core    core HP 
    public $core = null;

    //@var array  $players team
    public $players = [];

    //@var Config $config
    public $config = null;

    //@var API  $api  | Web Api Client |
    public $api = null;

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if(!file_exists($this->getDataFolder()){
            mkdir($this->getDataFolder(), 0744, true);
        }
        $this->setting = new Config($this->getDataFolder().'setting.yml', Config::YAML,
                                   [
                                    'hp'=>200, 
                                    'world'=>'world',
                                    'USE_WEB_API'=>'off',
                                    ]);
        $this->config = new Config($this->getDataFolder().'config.yml', Config::YAML, 
                                   [
                                    'ACCESS_TOKEN'=>'',
                                    'WEB_API_URL'=>'https://sample.com/api.php',
                                   ]);
        $file = $this->getDataFolder().'messages.ini';
        if(!file_exists($file)){
            $this->makeMessageFile($file); //メッセージファイル
        }
        $useApi = $this->getSettingData('USE_WEB_API'); //web api を使うかどうか
        switch($useApi){
            case 'on':
            case 'true':
            case 1:
                $key = $this->getAccessKeys();
                $this->api = new API($key['url'], $key['token']);
                if(!$this->api->isOk()){
                    $this->getLogger()->warning("§cYour \"web api\" is not working. Check your \"web api\"");
                    $this->getLogger()->notice("§cServer will close...");
                    $this->getServer()->close();
                break;
        }
        $this->loadGame();
    }

    public function onJoin(PlayerJoinEvent $ev){
        $player = $ev->getPlayer();
        $player->getInventory()->clearAll();
        $this->healAll($player);
        $this->setNameTag($player);
    }

    public function onTouch(PlayerInteractEvent $ev){
        $player = $ev->getPlayer();
        $block = $ev->getBlock();
        if($this->isJoinBlock($block)){
            if($player->isPlayer($player)){
                $player->sendMessage(self::getMessage('player.cant.join'));
                return false;
            }
            $this->joinGame($player);
            $player->sendMessage(self::getMessage('player.joined')); 
        }
        return true;
    }

 
    /**
     * @param  Player $player
     * @return void
     */

    public function setNameTag(Player $player){
        $name = $player->getName();
        if($this->isPlayer($player)){
            $team = $this->getTeam($player);
            $colors = ['§a', '§b'];
            $tag = $colors[$team];
            $name = "{$tag}{$name}";
        }
        $level = $this->getPlayerLevel($player);
        $name .= "§e|§f ".$level;
        
        $player->setNameTag($name);
    }    


    /**
     * @param  Player  $player 
     * @param  string  $option  Option | 'name' |
     * @return bool    $return
     * @return string  $return
     * @return int     $return
     */

    public function getTeam(Player $player, $option = false){
        $name = $player->getName();
        $return = '';
        if($option){
            if(in_array($this->players['red'], $name)){
                $return = 'red';
            }elseif(in_array($this->players['blue'], $name)){
                $return = 'blue';
            }else{
                $return = false;
            }
            return $return;
        }
        if(in_array($this->players['red'], $name)){
            $return = 0;
        }elseif(in_array($this->players['blue'], $name)){
            $return = 1;
        }else{
            $return = false;
        }
        return $return;
        
    }  

 
    /**
     * @param  Player  $player
     * @return bool
     */

    public function isPlayer(Player $player){
        $players = $this->getPlayers(); 
        $name = $player->getName();
        return in_array($players, $name);
    }


    /**
     * @param  Block   $block
     * @return bool
     */ 

    public function isJoinBlock(Block $block){
        //TODO
    }

    public function getPlayers(){
        $array = array_merge($this->players['red'], $this->players['blue']); 
        return $array;
    }

    public function getConfigData(string $key){
        if($this->config->exists($key)){
            return $this->config->get($key);
        }else{
            return false; 
        }
    }

    public function getSettingData(string $key){
        if($this->setting->exists($key)){
            return $this->setting->get($key);
        }else{
            return false; 
        }
    }

    public function loadGame(){
        $hp = $this->getData('hp');
        $this->setCoreHp($hp);
        
    }

    public function setCoreHp($hp){
        $this->core = ['RED'=>$hp, 'BLUE'=>$hp];
    }

    pulic function setFirstItems(Player $player){
        $items = [
                  Item::get(),
                 ];
        //TODO
    }

    public function getCoreHp($team){
        switch($team){
            case 'RED':
            case 'red':
            case 0:
                $return = $this->core['RED'];
                break;
            case 'BLUE':
            case 'blue':
            case 1:
                $return = $this->core['BLUE'];
                break;
            default:
                $return = false;
         }
         return $return;
     }

     public function decreHp($team){
         switch($team){
            case 'RED':
            case 'red':
            case 0:
                $hp = $this->getCoreHp(0);
                --$hp;
                $this->core['RED'] = $hp;
                break;
            case 'BLUE':
            case 'blue':
            case 1:
                $hp = $this->getCoreHp(1);
                --$hp;
                $this->core['BLUE'] = $hp;
                break;
            default:
                $return = false;
         }
     }

     public function getAccessKeys(){
         $return = ['url'=>$this->getConfigData('WEB_API_URL'), 'token'=>$this->getConfigData('ACCSESS_TOKEN')];
         return $return;
     }
}