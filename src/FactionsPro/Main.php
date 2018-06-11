<?php

namespace FactionsPro;

use pocketmine\math\Vector3;
use pocketmine\level\{Level, Position};
use pocketmine\plugin\PluginBase;
use pocketmine\command\{Command, CommandSender};
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\{PlayerJoinEvent, PlayerChatEvent};
use pocketmine\{Server, Player};
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\{Config, TextFormat};
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\block\Air;
use pocketmine\entity\{Skeleton, Pig, Chicken, Zombie, Creeper, Cow, Spider, Blaze, Ghast};

class Main extends PluginBase implements Listener {
    
    public $db;
    public $prefs;
    public $war_req = [];
    public $wars = [];
    public $war_players = [];
    public $antispam;
    public $purechat;
    public $esssentialspe;
    public $factionChatActive = [];
    public $allyChatActive = [];
    private $prefix = "§7[§6Void§bFactions§cPE§7]";
    
    const HEX_SYMBOL = "e29688";
    
    public function onLoad(): void{
	    $this->getLogger()->info("FactionsPro is being enabled - Please wait whilst our Loading system becomes visible.");
    }
    public function onEnable(): void{
        @mkdir($this->getDataFolder());
        if (!file_exists($this->getDataFolder() . "BannedNames.txt")) {
            $file = fopen($this->getDataFolder() . "BannedNames.txt", "w");
            $txt = "Admin:admin:Staff:staff:Owner:owner:Builder:builder:Op:OP:op";
            fwrite($file, $txt);
	    $this->getLogger()->info("FactionsPro has been enabled with success. If any errors popup after enabled, then let us know.");
        }
        $this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);
        $this->antispam = $this->getServer()->getPluginManager()->getPlugin("AntiSpamPro");
        if (!$this->antispam) {
            $this->getLogger()->info("AntiSpamPro is not installed. If you want to ban rude Faction names, then AntiSpamPro needs to be installed. Disabling Rude faction names system.");
        }
        $this->purechat = $this->getServer()->getPluginManager()->getPlugin("PureChat");
        if (!$this->purechat) {
            $this->getLogger()->info("PureChat is not installed. If you want to display Faction ranks in chat, then PureChat needs to be installed. Disabling Faction chat system.");
        }
        $this->essentialspe = $this->getServer()->getPluginManager()->getPlugin("EssentialsPE");
        if (!$this->essentialspe) {
            $this->getLogger()->info("EssentialsPE is not installed. If you want to use the new Faction Raiding system, then EssentialsPE needs to be installed. Disabling Raiding system.");
    	}
	$this->economyapi = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
	if (!$this->economyapi) {
	    $this->getLogger()->info("EconomyAPI is not installed. If you want to use the Faction Values system, then EconomyAPI needs to be installed. Disabling the Factions Value system.");
	}
	@mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
		$this->prefix = $this->getConfig()->get("pluginprefix", $this->prefix);
		if(sqrt($size = $this->getConfig()->get("PlotSize")) % 2 !== 0){
			$this->getLogger()->notice("Square Root Of Plot Size ($size) Must Not Be An unknown Number in the plugin! (The size was Currently: ".(sqrt($size = $this->prefs->get("PlotSize"))).")");
			$this->getLogger()->notice("Available Sizes: 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024");
			$this->getLogger()->notice("Plot Size Set To 16 automatically");
			$this->getConfig()->set("PlotSize", 16);
		}
        $this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS alliance (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, requestedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT, world TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS strength(faction TEXT PRIMARY KEY, power INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS allies(ID INT PRIMARY KEY,faction1 TEXT, faction2 TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS enemies(ID INT PRIMARY KEY,faction1 TEXT, faction2 TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS alliescountlimit(faction TEXT PRIMARY KEY, count INT);");
        
        $this->db->exec("CREATE TABLE IF NOT EXISTS balance(faction TEXT PRIMARY KEY, cash INT)");
        try{
            $this->db->exec("ALTER TABLE plots ADD COLUMN world TEXT default null");
            Server::getInstance()->getLogger()->info(TextFormat::GREEN . "FactionPro: Added 'world' column to plots");
        }catch(\ErrorException $ex){
        }
    }
    public function setEnemies($faction1, $faction2) {
        $stmt = $this->db->prepare("INSERT INTO enemies (faction1, faction2) VALUES (:faction1, :faction2);");
        $stmt->bindValue(":faction1", $faction1);
        $stmt->bindValue(":faction2", $faction2);
        $stmt->execute();
    }
    
    public function areEnemies($faction1, $faction2) {
        $result = $this->db->query("SELECT ID FROM enemies WHERE faction1 = '$faction1' AND faction2 = '$faction2';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if (empty($resultArr) == false) {
            return true;
        }
    }
    
    public function isInFaction($player) {
        $result = $this->db->query("SELECT player FROM master WHERE player='$player';");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }
    
    public function getFaction($player) {
        $faction = $this->db->query("SELECT faction FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["faction"];
    }
    
    public function setFactionPower($faction, $power) {
        if ($power < 0) {
            $power = 0;
        }
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":power", $power);
        $stmt->execute();
    }
    public function setAllies($faction1, $faction2) {
        $stmt = $this->db->prepare("INSERT INTO allies (faction1, faction2) VALUES (:faction1, :faction2);");
        $stmt->bindValue(":faction1", $faction1);
        $stmt->bindValue(":faction2", $faction2);
        $stmt->execute();
    }
    public function areAllies($faction1, $faction2) {
        $result = $this->db->query("SELECT ID FROM allies WHERE faction1 = '$faction1' AND faction2 = '$faction2';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if (empty($resultArr) == false) {
            return true;
        }
    }
    public function updateAllies($faction) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO alliescountlimit(faction, count) VALUES (:faction, :count);");
        $stmt->bindValue(":faction", $faction);
        $result = $this->db->query("SELECT ID FROM allies WHERE faction1='$faction';");
        $i = 0;
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $i = $i + 1;
        }
        $stmt->bindValue(":count", (int) $i);
        $stmt->execute();
    }
    public function getAlliesCount($faction) {
        $result = $this->db->query("SELECT count FROM alliescountlimit WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["count"];
    }
    public function getAlliesLimit() {
        return (int) $this->getConfig()->get("AllyLimitPerFaction");
    }
    public function deleteAllies($faction1, $faction2) {
        $stmt = $this->db->prepare("DELETE FROM allies WHERE faction1 = '$faction1' AND faction2 = '$faction2';");
        $stmt->execute();
    }
    public function getFactionPower($faction) {
        $result = $this->db->query("SELECT power FROM strength WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["power"];
    }
    public function addFactionPower($faction, $power) {
        if ($this->getFactionPower($faction) + $power < 0) {
            $power = $this->getFactionPower($faction);
        }
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":power", $this->getFactionPower($faction) + $power);
        $stmt->execute();
    }
    public function subtractFactionPower($faction, $power) {
        if ($this->getFactionPower($faction) - $power < 0) {
            $power = $this->getFactionPower($faction);
        }
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":power", $this->getFactionPower($faction) - $power);
        $stmt->execute();
    }
    public function isLeader($player) {
        $faction = $this->db->query("SELECT rank FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["rank"] == "Leader";
    }
    public function isOfficer($player) {
        $faction = $this->db->query("SELECT rank FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["rank"] == "Officer";
    }
    public function isMember($player) {
        $faction = $this->db->query("SELECT rank FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["rank"] == "Member";
    }
    public function getPlayersInFactionByRank($s, $faction, $rank) {
        if ($rank != "Leader") {
            $rankname = $rank . 's';
        } else {
            $rankname = $rank;
        }
        $team = "";
        $result = $this->db->query("SELECT player FROM master WHERE faction='$faction' AND rank='$rank';");
        $row = array();
        $i = 0;
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $row[$i]['player'] = $resultArr['player'];
            if ($this->getServer()->getPlayer($row[$i]['player']) instanceof Player) {
                $team .= TextFormat::ITALIC . TextFormat::AQUA . $row[$i]['player'] . TextFormat::GREEN . "[ON]" . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
            } else {
                $team .= TextFormat::ITALIC . TextFormat::AQUA . $row[$i]['player'] . TextFormat::RED . "[OFF]" . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
            }
            $i = $i + 1;
        }
        $s->sendMessage($this->formatMessage("~ *<$rankname> of |$faction|* ~", true));
        $s->sendMessage($team);
    }
    public function getAllAllies($s, $faction) {
        $team = "";
        $result = $this->db->query("SELECT faction2 FROM allies WHERE faction1='$faction';");
        $row = array();
        $i = 0;
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $row[$i]['faction2'] = $resultArr['faction2'];
            $team .= TextFormat::ITALIC . TextFormat::GREEN . $row[$i]['faction2'] . TextFormat::RESET . TextFormat::WHITE . "§2,§a " . TextFormat::RESET;
            $i = $i + 1;
        }
	$allies = $this->getConfig()->get("OurAllies");
        $s->sendMessage($this->formatMessage("$allies", true));
        $s->sendMessage($team);
    }
    public function sendListOfTop10FactionsTo($s) {
        $tf = "";
        $result = $this->db->query("SELECT faction FROM strength ORDER BY power DESC LIMIT 10;");
        $row = array();
        $i = 0;
	$topstr = $this->getConfig()->get("TopSTR");
        $s->sendMessage("$topstr", true);
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $j = $i + 1;
            $cf = $resultArr['faction'];
            $pf = $this->getFactionPower($cf);
            $df = $this->getNumberOfPlayers($cf);
            $s->sendMessage(TextFormat::ITALIC . TextFormat::GOLD . "§6§l$j -> " . TextFormat::GREEN . "§r§d$cf" . TextFormat::GOLD . " §b| " . TextFormat::RED . "§e$pf STR" . TextFormat::GOLD . " §b| " . TextFormat::LIGHT_PURPLE . "§a$df/50" . TextFormat::RESET);
            $i = $i + 1;
        }
    }
    public function getPlayerFaction($player) {
        $faction = $this->db->query("SELECT faction FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["faction"];
    }
    public function getLeader($faction) {
        $leader = $this->db->query("SELECT player FROM master WHERE faction='$faction' AND rank='Leader';");
        $leaderArray = $leader->fetchArray(SQLITE3_ASSOC);
        return $leaderArray['player'];
    }
    public function factionExists($faction) {
        $result = $this->db->query("SELECT player FROM master WHERE faction='$faction';");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }
    public function sameFaction($player1, $player2) {
        $faction = $this->db->query("SELECT faction FROM master WHERE player='$player1';");
        $player1Faction = $faction->fetchArray(SQLITE3_ASSOC);
        $faction = $this->db->query("SELECT faction FROM master WHERE player='$player2';");
        $player2Faction = $faction->fetchArray(SQLITE3_ASSOC);
        return $player1Faction["faction"] == $player2Faction["faction"];
    }
    public function getNumberOfPlayers($faction) {
        $query = $this->db->query("SELECT COUNT(player) as count FROM master WHERE faction='$faction';");
        $number = $query->fetchArray();
        return $number['count'];
    }
    public function isFactionFull($faction) {
        return $this->getNumberOfPlayers($faction) >= $this->getConfig()->get("MaxPlayersPerFaction");
    }
    public function isNameBanned($name) {
        $bannedNames = file_get_contents($this->getDataFolder() . "BannedNames.txt");
        $isbanned = false;
        if (isset($name) && $this->antispam && $this->antispam->getProfanityFilter()->hasProfanity($name)) $isbanned = true;
        return (strpos(strtolower($bannedNames), strtolower($name)) > 0 || $isbanned);
    }
    public function newPlot($faction, $x1, $z1, $x2, $z2) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO plots (faction, x1, z1, x2, z2) VALUES (:faction, :x1, :z1, :x2, :z2);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":x1", $x1);
        $stmt->bindValue(":z1", $z1);
        $stmt->bindValue(":x2", $x2);
        $stmt->bindValue(":z2", $z2);
        $stmt->execute();
    }
    public function drawPlot($sender, $faction, $x, $y, $z, $level, $size) {
        $arm = ($size - 1) / 2;
        $block = new Air();
        if ($this->cornerIsInPlot($x + $arm, $z + $arm, $x - $arm, $z - $arm)) {
            $claimedBy = $this->factionFromPoint($x, $z);
            $power_claimedBy = $this->getFactionPower($claimedBy);
            $power_sender = $this->getFactionPower($faction);
           
	    if ($this->prefs->get("EnableOverClaim")) {
                if ($power_sender < $power_claimedBy) {
	            $prefix = $this->getConfig()->get("pluginprefix");
		    $noclaim = $this->getConfig()->get("NotEnoughToOC");
                    $sender->sendMessage($this->formatMessage("$prefix $noclaim", true));
                } else {
		    $prefix = $this->getConfig()->get("pluginprefix");
		    $yesclaim = $this->getConfig()->get("EnoughToOverClaim");
                    $sender->sendMessage($this->formatMessage("$prefix $yesclaim", true));
                }
                return false;
            } else {
		$prefix = $this->getConfig()->get("pluginprefix");
		$ocmessage = $this->getConfig()->get("DisabledMessage");
                $sender->sendMessage($this->formatMessage("$prefix $ocmessage", true));
                return false;
	    }
        }
        $this->newPlot($faction, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
        return true;
    }
    public function isInPlot($player) {
        $x = $player->getFloorX();
        $z = $player->getFloorZ();
        $result = $this->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }
    public function factionFromPoint($x, $z) {
        $result = $this->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return $array["faction"];
    }
    public function inOwnPlot($player) {
        $playerName = $player->getName();
        $x = $player->getFloorX();
        $z = $player->getFloorZ();
        return $this->getPlayerFaction($playerName) == $this->factionFromPoint($x, $z);
    }
    public function pointIsInPlot($x, $z) {
        $result = $this->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return !empty($array);
    }
    public function cornerIsInPlot($x1, $z1, $x2, $z2) {
        return($this->pointIsInPlot($x1, $z1) || $this->pointIsInPlot($x1, $z2) || $this->pointIsInPlot($x2, $z1) || $this->pointIsInPlot($x2, $z2));
    }
    public function formatMessage($string, $confirm = false) {
        if ($confirm) {
            return TextFormat::GREEN . "$string"; //We'll try configuring this in the near future.
        } else {
            return TextFormat::YELLOW . "$string"; //We'll try configuring this in the near future.
        }
    }
    public function motdWaiting($player) {
        $stmt = $this->db->query("SELECT player FROM motdrcv WHERE player='$player';");
        $array = $stmt->fetchArray(SQLITE3_ASSOC);
        return !empty($array);
    }
    public function getMOTDTime($player) {
        $stmt = $this->db->query("SELECT timestamp FROM motdrcv WHERE player='$player';");
        $array = $stmt->fetchArray(SQLITE3_ASSOC);
        return $array['timestamp'];
    }
    public function setMOTD($faction, $player, $msg) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO motd (faction, message) VALUES (:faction, :message);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":message", $msg);
        $result = $stmt->execute();
        $this->db->query("DELETE FROM motdrcv WHERE player='$player';");
    }
    public function getMapBlock(){
        
    $symbol = hex2bin(self::HEX_SYMBOL);
        
    return $symbol;
    }
    public function getBalance($faction){
		$stmt = $this->db->query("SELECT * FROM balance WHERE `faction` LIKE '$faction';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		if(!$array){
			$this->setBalance($faction, $this->prefs->get("defaultFactionBalance", 0));
			$this->getBalance($faction);
		}
		return $array["cash"];
	}
	public function setBalance($faction, int $money){
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO balance (faction, cash) VALUES (:faction, :cash);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":cash", $money);
		return $stmt->execute();
	}
	public function addToBalance($faction, int $money){
		if($money < 0) return false;
		return $this->setBalance($faction, $this->getBalance($faction) + $money);
	}
	public function takeFromBalance($faction, int $money){
		if($money < 0) return false;
		return $this->setBalance($faction, $this->getBalance($faction) - $money);
	}
	public function sendListOfTop10RichestFactionsTo(Player $s){
        $result = $this->db->query("SELECT * FROM balance ORDER BY cash DESC LIMIT 10;");
        $i = 0;
        $prefix = $this->getConfig()->get("pluginprefix");
	$topmoney = $this->getConfig()->get("TopMoney");
        $s->sendMessage("$prefix $topmoney", true);
        while($resultArr = $result->fetchArray(SQLITE3_ASSOC)){
        	var_dump($resultArr);
            $j = $i + 1;
            $cf = $resultArr['faction'];
            $pf = $resultArr['cash'];
            $s->sendMessage(TextFormat::BOLD.TextFormat::GOLD.$j.". ".TextFormat::RESET.TextFormat::AQUA.$cf.TextFormat::RED.TextFormat::BOLD." §c- ".TextFormat::LIGHT_PURPLE."§d$".$pf);
            $i = $i + 1;
        } 
	}
    public function getFactionByPlayer($player){
        $player = $player->getName();
        return $this->getFaction($player);
    }
    /**
     * @param Player $player
     * @return string
     */
    public function getPlayerRank($player){
        $player = $player->getName();
	    $officerbadge = $this->getConfig()->get("OfficerBadge");
	    $leaderbadge = $this->getConfig()->get("LeaderBadge");
        if($this->isInFaction($player->getName()))
	{
            if($this->isOfficer($player)) {
                return '$officerbadge';
            }
            elseif($this->isLeader($player)){
                return '$leaderbadge';
	    }
    	}
    }
	public function getSpawnerPrice(string $type) : int {
		$sp = $this->getConfig()->get("spawnerPrices");
		if(isset($sp[$type])) return $sp[$type];
		return 0;
	}
	public function getEconomy(){
		$pl = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		if(!$pl) return $pl;
		if(!$pl->isEnabled()) return null;
		return $pl;
	}
    
    // ASCII Map
	CONST MAP_WIDTH = 50;
	CONST MAP_HEIGHT = 11;
	CONST MAP_HEIGHT_FULL = 17;
	CONST MAP_KEY_CHARS = "\\/#?ç¬£$%=&^ABCDEFGHJKLMNOPQRSTUVWXYZÄÖÜÆØÅ1234567890abcdeghjmnopqrsuvwxyÿzäöüæøåâêîûô";
	CONST MAP_KEY_WILDERNESS = TextFormat::GRAY . "-"; /*Del*/
	CONST MAP_KEY_SEPARATOR = TextFormat::AQUA . "*"; /*Del*/
	CONST MAP_KEY_OVERFLOW = TextFormat::WHITE . "-" . TextFormat::WHITE; # ::MAGIC?
	CONST MAP_OVERFLOW_MESSAGE = self::MAP_KEY_OVERFLOW . ": Too Many Factions (>" . 107 . ") on this Map.";
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if ($sender instanceof Player) {
            $playerName = $sender->getPlayer()->getName(); //Sender who executes the command.
	    $prefix = $this->getConfig()->get("pluginprefix"); //Prefix configurations.
            if (strtolower($command->getName()) === "f") {
                if (empty($args)) {
                    $sender->sendMessage($this->formatMessage($this->getConfig()->get("pluginprefix "). $this->getConfig()->get("helpmessage")));
                    return true;
                }
                    ///////////////////////////////// WAR /////////////////////////////////
                    if(strtolower($args[0]) == "war" or strtolower($args[0]) == "wr"){
                        if (!isset($args[1])) {
			    $warusage = $this->getConfig()->get("warcommand");
                            $sender->sendMessage($this->formatMessage($this->getConfig()->get("pluginprefix "). $this->getConfig()->get("warcommand")));
                            return true;
                        }
                        if (strtolower($args[1]) == "tp" or strtolower($args[1]) == "teleport") {
                            foreach ($this->wars as $r => $f) {
                                $fac = $this->getPlayerFaction($playerName);
                                if ($r == $fac) {
                                    $x = mt_rand(0, $this->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->war_players[$f][$x];
                                    $sender->teleport($this->getServer()->getPlayerByName($tper));
                                    return true;
                                }
                                if ($f == $fac) {
                                    $x = mt_rand(0, $this->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->war_players[$r][$x];
                                    $sender->teleport($this->getServer()->getPlayer($tper));
                                    return true;
                                }
                            }
                            $sender->sendMessage("$prefix §cYou must be in a war to do that");
                            return true;
                        }
                        if (!($this->alphanum($args[1]))) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou may only use letters and numbers"));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe Faction named §4$args[1] §cdoes not exist"));
                            return true;
                        }
                        if (!$this->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cOnly your faction leader may start wars"));
                            return true;
                        }
                        if (!$this->areEnemies($this->getPlayerFaction($playerName), $args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction is not an enemy of §4$args[1]"));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $sFaction = $this->getPlayerFaction($playerName);
                            foreach ($this->war_req as $r => $f) {
                                if ($r == $args[1] && $f == $sFaction) {
                                    foreach ($this->getServer()->getOnlinePlayers() as $p) {
                                        $task = new FactionWar($this->plugin, $r);
                                        $handler = $this->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                        $task->setHandler($handler);
                                        $p->sendMessage("§bThe war against §a$factionName §band §a$sFaction §bhas started!");
                                        if ($this->getPlayerFaction($p->getName()) == $sFaction) {
                                            $this->war_players[$sFaction][] = $p->getName();
                                        }
                                        if ($this->getPlayerFaction($p->getName()) == $factionName) {
                                            $this->war_players[$factionName][] = $p->getName();
                                        }
                                    }
                                    $this->wars[$factionName] = $sFaction;
                                    unset($this->war_req[strtolower($args[1])]);
                                    return true;
                                }
                            }
                            $this->war_req[$sFaction] = $factionName;
                            foreach ($this->getServer()->getOnlinePlayers() as $p) {
                                if ($this->getPlayerFaction($p->getName()) == $factionName) {
                                    if ($this->getLeader($factionName) == $p->getName()) {
                                        $p->sendMessage("§3$sFaction §bwants to start a war. Please use: §3'/f $args[0] $sFaction' §bto commence the war!");
                                        $sender->sendMessage("$prefix §aThe Faction war has been requested. §bPlease wait for their response.");
                                        return true;
                                    }
                                }
                            }
                            $sender->sendMessage("$prefix §cFaction leader is not online.");
                            return true;
                        }
                    }
                    /////////////////////////////// CREATE ///////////////////////////////
                    if(strtolower($args[0]) == "create" or strtolower($args[0]) == "make"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction name>"));
			    $sender->sendMessage($this->formatMessage("$prefix §b§aDescription: §dCreates a faction."));
                            return true;
                        }
                        if (!($this->alphanum($args[1]))) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou may only use letters and numbers"));
                            return true;
                        }
                        if ($this->isNameBanned($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe name §4$args[1] §cis not allowed"));
                            return true;
                        }
                        if ($this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe Faction named §4$args[1] §calready exists"));
                            return true;
                        }
                        if (strlen($args[1]) > $this->getConfig()->get("MaxFactionNameLength")) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThat name is too long, please try again"));
                            return true;
                        }
                        if ($this->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must leave the faction first"));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $rank = "Leader";
                            $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", $playerName);
                            $stmt->bindValue(":faction", $factionName);
                            $stmt->bindValue(":rank", $rank);
                            $result = $stmt->execute();
                            $this->updateAllies($factionName);
                            $this->setFactionPower($factionName, $this->getConfig()->get("TheDefaultPowerEveryFactionStartsWith"));
			    $this->setBalance($factionName, $this->getConfig()->get("defaultFactionBalance"));
                            $this->getServer()->broadcastMessage("§a$playerName §bhas created a faction named §c$factionName");
                            $sender->sendMessage($this->formatMessage("$prefix §bYour Faction named §a$factionName §bhas been created. §6Next, use /f desc to make a faction description.", true));
			    var_dump($this->db->query("SELECT * FROM balance;")->fetchArray(SQLITE3_ASSOC));
                            return true;
                        }
                    }
                    /////////////////////////////// INVITE ///////////////////////////////
                    if(strtolower($args[0]) == "invite" or strtolower($args[0]) == "inv"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("$prefix §bPlease use: §3/f $args[0] <player>\n§aDescription: §dInvites a player to your faction."));
                            return true;
                        }
                        if ($this->isFactionFull($this->getPlayerFaction($playerName))) {
                            $sender->sendMessage($this->formatMessage($this->getConfig()->get("pluginprefix "). $this->getConfig()->get("invite_facfull")));
                            return true;
                        }
                        $invited = $this->getServer()->getPlayer($args[1]);
                        if (!($invited instanceof Player)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe player named §4$args[1] §cis currently not online"));
                            return true;
                        }
                        if ($this->isInFaction($invited->getName()) == true) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe player named §4$args[1] §cis already in a faction"));
                            return true;
                        }
                        if ($this->getConfig()->get("OnlyLeadersAndOfficersCanInvite")) {
                            if (!($this->isOfficer($playerName) || $this->isLeader($playerName))) {
                                $sender->sendMessage($this->formatMessage("$prefix §cOnly your faction leader/officers can invite"));
                                return true;
                            }
                        }
                        if ($invited->getName() == $playerName) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou can't invite yourself to your own faction"));
                            return true;
                        }
                        $factionName = $this->getPlayerFaction($playerName);
                        $invitedName = $invited->getName();
                        $rank = "Member";
                        $stmt = $this->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                        $stmt->bindValue(":player", $invitedName);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":invitedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->formatMessage("$prefix §a$invitedName §bhas been invited succesfully! §5Wait for $invitedName 's response.", true));
                        $invited->sendMessage($this->formatMessage("$prefix §bYou have been invited to §a$factionName. §bType §3'/f accept / yes' or '/f deny / no' §binto chat to accept or deny!", true));
                    }
                    /////////////////////////////// LEADER ///////////////////////////////
                    if (strtolower($args[0]) == "leader" or strtolower($args[0]) == "transferleader"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <player>\n§aDescription: §dMake someone else leader of the faction."));
                            return true;
                        }
                        if (!$this->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to use this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to use this"));
                            return true;
                        }
                        if ($this->getPlayerFaction($playerName) != $this->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou need to add the player: §4$args[1] §cto faction first"));
                            return true;
                        }
                        if (!($this->getServer()->getPlayer($args[1]) instanceof Player)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe player named §4$args[1] §cis currently not online"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou can't transfer the leadership to yourself"));
                            return true;
                        }
                        $factionName = $this->getPlayerFaction($playerName);
                        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $playerName);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Leader");
                        $result = $stmt->execute();
                        $sender->sendMessage($this->formatMessage("$prefix §aYou are no longer leader. §bYou made §a$args[1] §bThe leader of this faction", true));
                        $this->getServer()->getPlayer($args[1])->sendMessage($this->formatMessage("§aYou are now leader \nof §3$factionName!", true));
                    }
                    /////////////////////////////// PROMOTE ///////////////////////////////
                    if ($args[0] == "promote" or $args[0] == "pm2") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <player>\n§aDescription: §dPromote a player from your faction."));
                            return true;
                        }
                        if (!$this->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to use this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to use this"));
                            return true;
                        }
                        if ($this->getPlayerFaction($playerName) != $this->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe player named: §4$args[1] §cis not in this faction"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou can't promote yourself"));
                            return true;
                        }
                        if ($this->isOfficer($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe player named §4$args[1] §cis already an Officer of this faction"));
                            return true;
                        }
                        $factionName = $this->getPlayerFaction($playerName);
                        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Officer");
                        $result = $stmt->execute();
                        $promotee = $this->getServer()->getPlayer($args[1]);
                        $sender->sendMessage($this->formatMessage("$prefix §a$promotee §bhas been promoted to Officer", true));
                        if ($promotee instanceof Player) {
                            $promotee->sendMessage($this->formatMessage("$prefix §bYou were promoted to officer of §a$factionName!", true));
                            return true;
                        }
                    }
                    /////////////////////////////// DEMOTE ///////////////////////////////
                    if ($args[0] == "demote" or $args[0] == "dm2") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <player>\n§aDescription: §dDemote a player from your faction"));
                            return true;
                        }
                        if ($this->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to use this"));
                            return true;
                        }
                        if ($this->isLeader($playerName) == false) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to use this"));
                            return true;
                        }
                        if ($this->getPlayerFaction($playerName) != $this->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe player named: §4$args[1] §cis not in this faction"));
                            return true;
                        }
                        if ($args[1] == $playerName) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou can't demote yourself"));
                            return true;
                        }
                        if (!$this->isOfficer($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe player named §4$args[1] §cis already a Member of this faction"));
                            return true;
                        }
                        $factionName = $this->getPlayerFaction($playerName);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $demotee = $this->getServer()->getPlayer($args[1]);
                        $sender->sendMessage($this->formatMessage("$prefix §5$demotee §2has been demoted to Member", true));
                        if ($demotee instanceof Player) {
                            $demotee->sendMessage($this->formatMessage("$prefix §2You were demoted to member of §5$factionName!", true));
                            return true;
                        }
                    }
                    /////////////////////////////// KICK ///////////////////////////////
                    if(strtolower($args[0]) == "kick" or strtolower($args[0]) == "k"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <player>\n§aDescription: §dKicks a player from a faction."));
                            return true;
                        }
                        if ($this->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to use this"));
                            return true;
                        }
                        if ($this->isLeader($playerName) == false) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to use this"));
                            return true;
                        }
                        if ($this->getPlayerFaction($playerName) != $this->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe Player named §4$args[1] §cis not in this faction"));
                            return true;
                        }
                        if ($playerName == $args[1]) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou can't kick yourself"));
                            return true;
                        }
                        if($this->factionChatActive[$playerName]) {
                       	unset($this->factionChatActive[$playerName]);
                        }
                        if ($this->allyChatActive[$playerName]) {
                        unset($this->allyChatActive[$playerName]);
                        }
                        $kicked = $this->getServer()->getPlayer($args[1]);
                        $factionName = $this->getPlayerFaction($playerName);
                        $this->db->query("DELETE FROM master WHERE player='$kicked';");
                        $sender->sendMessage($this->formatMessage("$prefix §aYou successfully kicked §2$kicked", true));
                        $this->subtractFactionPower($factionName, $this->getConfig()->get("PowerGainedPerPlayerInFaction"));
			$this->takeFromBalance($factionName, $this->getConfig()->get("MoneyGainedPerPlayerInFaction"));
                        if ($kicked instanceof Player) {
                            $kicked->sendMessage($this->formatMessage("$prefix §bYou have been kicked from \n §a$factionName", true));
                            return true;
                        }
                    }
                    /////////////////////////////// CLAIM ///////////////////////////////
                    if(strtolower($args[0]) == "claim" or strtolower($args[0]) == "cl"){
				if($this->getConfig()->get("ClaimingEnabled") == false){
					$sender->sendMessage($this->formatMessage("$prefix §cPlots are not enabled on this server."));
					return true;
			}
			if(!$this->isInFaction($playerName)){
			   $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction."));
			   return true;
			}
                        if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->getConfig()->get("ClaimWorlds"))) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou can only claim in Faction Worlds: " . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                            return true;
                        }
                        if ($this->inOwnPlot($sender)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction has already claimed this area."));
                            return true;
                        }
                        $faction = $this->getPlayerFaction($sender->getPlayer()->getName());
                        if ($this->getNumberOfPlayers($faction) < $this->getConfig()->get("PlayersNeededInFactionToClaimAPlot")) {
                            $needed_players = $this->getConfig()->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->etNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage("$prefix §cYou need §4$needed_players §cmore players in your faction to claim a faction plot"));
                            return true;
                        }
                        if ($this->getFactionPower($faction) < $this->getConfig()->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->getConfig()->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->getConfig()->getFactionPower($faction);
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction doesn't have enough STR to claim a land."));
                            $sender->sendMessage($this->formatMessage("$prefix §4$needed_power §cSTR is required but your faction has only §4$faction_power §cSTR."));
                            return true;
			}
                        if ($this->getBalance($faction) < $this->getConfig()->get("MoneyNeededToClaimAPlot")) {
                            $needed_money = $this->getConfig()->get("MoneyNeededToClaimAPlot");
                            $balance = $this->getBalance($faction);
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction doesn't have enough Money to claim a land."));
                            $sender->sendMessage($this->formatMessage("$prefix §4$needed_money §cMoney is required but your faction has only §4$balance §cMoney."));
                            return true;
                        }
                        $x = floor($sender->getX());
			$y = floor($sender->getY());
			$z = floor($sender->getZ());
			$faction = $this->getPlayerFaction($sender->getPlayer()->getName());
			if(!$this->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->getConfig()->get("PlotSize"))){
				return true;
                        }
			$plot_size = $this->getConfig()->get("PlotSize");
                        $faction_power = $this->getFactionPower($faction);
                        $balance = $this->getBalance($faction);
			$this->subtractFactionPower($faction, $this->getConfig()->get("PowerNeededToClaimAPlot"));
                        $this->takeFromBalance($faction, $this->getConfig()->get("MoneyNeededToClaimAPlot"));
                        $this->getServer()->broadcastMessage("§aThe player §b$playerName §afrom §b$faction §3has claimed their land");
                        $sender->sendMessage($this->formatMessage("$prefix §bYour Faction plot has been claimed.", true));
		    }
                    if(strtolower($args[0]) == "plotinfo" or strtolower($args[0]) == "pinfo"){
                        $x = floor($sender->getX());
			$y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if (!$this->isInPlot($sender)) {
                            $sender->sendMessage($this->formatMessage("$prefix §5This plot is not claimed by anyone. §dYou can claim it by typing §5/f claim\n§dAlias Command: §5/f cl", true));
			    return true;
			}
                        $fac = $this->factionFromPoint($x, $z);
                        $power = $this->getFactionPower($fac);
                        $balance = $this->getBalance($fac);
                        $sender->sendMessage($this->formatMessage("$prefix §bThis plot is claimed by §a$fac §bwith §a$power §aSTR, §band §a$balance §bMoney"));
			return true;
                    }
                    if(strtolower($args[0]) == "forcedelete" or strtolower($args[0]) == "fdisband"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction>\n§aDescription: §dForce deletes a faction. For Operators only."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->formatMessage("$prefix §4§lYou must be OP to do this."));
                            return true;
                        }
                        $this->db->query("DELETE FROM master WHERE faction='$args[1]';");
                        $this->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        $this->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                        $this->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                        $this->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                        $this->db->query("DELETE FROM home WHERE faction='$args[1]';");
		        $this->db->query("DELETE FROM balance WHERE faction='$args[1]';");
		            	if ($this->factionChatActive[$playerName]) {
                        unset($this->factionChatActive[$playerName]);
		            	}
                        if ($this->allyChatActive[$playerName]) {
                        unset($this->allyChatActive[$playerName]);
	                $this->getServer()->broadcastMessage("§4$playerName §chas forcefully deleted the faction named §4$args[1]");
                        $sender->sendMessage($this->formatMessage("$prefix §aUnwanted faction was successfully deleted and their faction plot was unclaimed!", true));
                    }
                    if (strtolower($args[0]) == "addstrto" or strtolower($args[0]) == "addpower") {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction> <STR>\n§aDescription: §dAdds STR to a faction."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->formatMessage("$prefix §4§lYou must be OP to do this."));
                            return true;
                        }
                        $this->addFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->formatMessage("$prefix §bSuccessfully added §a$args[2] §bSTR to §a$args[1]", true));
                    }
                    if (strtolower($args[0]) == "addbalto" or strtolower($args[0]) == "addmoney") {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction> <money>\n§aDescription: §dAdds Money to a faction."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->formatMessage("$prefix §4§lYou must be OP to do this."));
                            return true;
                        }
                        $this->addToBalance($args[1], $args[2]);
                        $sender->sendMessage($this->formatMessage("$prefix §bSuccessfully added §a$args[2] §bBalance to §a$args[1]", true));
                    }
		    if (strtolower($args[0]) == "rmbalto" or strtolower($args[0]) == "rmmoney") {
	                if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction> <money>\n§aDescription: §dRemoves Money from a faction."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->formatMessage("$prefix §4§lYou must be OP to do this."));
                            return true;
                        }
                        $this->takeFromBalance($args[1], $args[2]);
                        $sender->sendMessage($this->formatMessage("$prefix §bSuccessfully removed §a$args[2] §bBalance from §a$args[1]", true));
		    }
                    if(strtolower($args[0]) == "rmpower" or strtolower($args[0]) == "rmstrto") {
		       if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction> <power>\n§aDescription: §dRemoves Power from a faction."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->formatMessage("$prefix §4§lYou must be OP to do this."));
                            return true;
                        }
                        $this->subtractFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->formatMessage("$prefix §bSuccessfully removed §a$args[2] §bPower from §a$args[1]", true));
                    }
                    if(strtolower($args[0]) == "playerfaction" or strtolower($args[0]) == "pf"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <player>\n§aDescription: §dCheck to see what faction a player's in."));
                            return true;
                        }
                        if (!$this->isInFaction($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe player named §4$args[1] §cis not in a faction or doesn't exist."));
                            return true;
                        }
                        $faction = $this->getPlayerFaction($args[1]);
			$playerName = $this->getServer()->getPlayer($args[1]);
                        $sender->sendMessage($this->formatMessage("$prefix §a-$playerName §bis in the faction: §a$faction-", true));
                    }
                    
                    if (strtolower($args[0]) == "overclaim" or strtolower($args[0]) == "oc"){
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction."));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to use this."));
                            return true;
                        }
                        $faction = $this->getPlayerFaction($playerName);
                        if ($this->getNumberOfPlayers($faction) < $this->getConfig()->get("PlayersNeededInFactionToClaimAPlot")) {
                            $needed_players = $this->getConfig()->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->formatMessage("$prefix §cYou need §4$needed_players §cmore players in your faction to overclaim a faction plot"));
                            return true;
                        }
                        if ($this->getFactionPower($faction) < $this->getConfig()->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->getConfig()->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->getFactionPower($faction);
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction doesn't have enough STR to claim a land."));
                            $sender->sendMessage($this->formatMessage("$prefix §4$needed_power §cSTR is required but your faction has only §4$faction_power §cSTR."));
                            return true;
                        }
                        $sender->sendMessage($this->formatMessage("$prefix §5Getting your coordinates...", true));
                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if ($this->getConfig()->get("EnableOverClaim")) {
                            if ($this->isInPlot($sender)) {
                                $faction_victim = $this->factionFromPoint($x, $z);
                                $faction_victim_power = $this->getFactionPower($faction_victim);
                                $faction_ours = $this->getPlayerFaction($playerName);
                                $faction_ours_power = $this->getFactionPower($faction_ours);
                                if ($this->inOwnPlot($sender)) {
                                    $sender->sendMessage($this->formatMessage("$prefix §cYou can't overclaim your own plot. It's already claimed in the coorinates: $x $y $z"));
                                    return true;
                                } else {
                                    if ($faction_ours_power < $faction_victim_power) {
                                        $sender->sendMessage($this->formatMessage("$prefix §cYou can't overclaim the plot of §4$faction_victim §cbecause your STR is lower than theirs."));
                                        return true;
                                    } else {
                                        $this->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->getConfig()->get("PlotSize")) - 1) / 2;
                                        $this->newPlot($faction_ours, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
			                $this->getServer()->broadcastMessage("§aPlayer §2$playerName §afrom §b$faction_ours §ahave overclaimed §b$faction_victim");
                                        $sender->sendMessage($this->formatMessage("$prefix §bThe faction plot of §3$faction_victim §bhas been over claimed! It is now yours.", true));
                                        return true;
                                    }
                                }
                            } else {
                                $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction plot."));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cOverclaiming is disabled."));
                            return true;
                        }
                    }
                    /////////////////////////////// UNCLAIM ///////////////////////////////
                    if(strtolower($args[0]) == "unclaim" or strtolower($args[0]) == "uncl"){
				  if($this->getConfig()->get("ClaimingEnabled") == false){
					$sender->sendMessage($this->formatMessage("$prefix §cFaction Plots are not enabled on this server."));
					return true;
                        }
			if(!$this->isInFaction($playerName)){
			   $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction."));
			   return true;
			}
                        if (!$this->isLeader($sender->getName())) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to use this"));
                            return true;
                        }
                        $faction = $this->getPlayerFaction($sender->getName());
                        $this->db->query("DELETE FROM plots WHERE faction='$faction';");
	                $this->getServer()->broadcastMessage("§aThe player: §2$playerName §afrom §b$faction §ahas unclaimed their plot!");
                        $sender->sendMessage($this->formatMessage("$prefix §bYour land has been unclaimed! It is no longer yours.", true));
                    }
                    /////////////////////////////// DESCRIPTION ///////////////////////////////
                    if(strtolower($args[0]) == "desc" or strtolower($args[0]) == "motd"){
                        if ($this->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to use this!"));
                            return true;
                        }
                        if ($this->isLeader($playerName) == false) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to use this"));
                            return true;
                        }
                        $sender->sendMessage($this->formatMessage("$prefix §dType your message in chat. It will not be visible to other players", true));
                        $stmt = $this->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                        $stmt->bindValue(":player", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
		    }
		    /////////////////////////////// TOP, also by @PrimusLV //////////////////////////
					if(strtolower($args[0]) == "top" or strtolower($args[0]) == "tf"){
					          if(!isset($args[1])){
					          $sender->sendMessage($this->formatMessage("$prefix §aPlease use: §a/f $args[0] money §d- To check top 10 Richest Factions on the server\n$prefix §aPlease use: §b/f $args[0] str §d- To check Top 10 BEST Factions (Highest STR)"));
                            		          return true;
			      		          }
						    
					          if(isset($args[1]) && $args[1] == "money"){
                              $this->sendListOfTop10RichestFactionsTo($sender);
					          }else{
					          if(isset($args[1]) && $args[1] == "str"){
                              $this->sendListOfTop10FactionsTo($sender);
						           //$this->plugin->sendListOfTop10RichestFactionsTo($sender);
			                          }
			                          return true;
		                          }
                                        }
                    /////////////////////////////// ACCEPT ///////////////////////////////
                    if(strtolower($args[0]) == "accept" or strtolower($args[0]) == "yes"){
                        $lowercaseName = strtolower($playerName);
                        $result = $this->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou have not been invited to any factions"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
			$inviteTime = $this->getConfig()->get("InviteTime");
                        if (($currentTime - $invitedTime) <= $inviteTime) { //Done.
                            $faction = $array["faction"];
                            $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", ($playerName));
                            $stmt->bindValue(":faction", $faction);
                            $stmt->bindValue(":rank", "Member");
                            $result = $stmt->execute();
                            $this->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->formatMessage("$prefix §aYou successfully joined §2$faction", true));
                            $this->addFactionPower($faction, $this->getConfig()->get("PowerGainedPerPlayerInFaction"));
			    $this->addToBalance($faction, $this->getConfig()->get("MoneyGainedPerPlayerInFaction"));
                            $this->getServer()->getPlayer($array["invitedby"])->sendMessage($this->formatMessage("$prefix §2$playerName §ajoined the faction", true));
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cInvite has expired."));
                            $this->db->query("DELETE FROM confirm WHERE player='$playerName';");
                        }
                    }
                    /////////////////////////////// DENY ///////////////////////////////
                    if(strtolower($args[0]) == "deny" or strtolower($args[0]) == "no"){
                        $lowercaseName = strtolower($playerName);
                        $result = $this->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou have not been invited to any factions"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
			$inviteTime = $this->getConfig()->get("InviteTime");
                        if (($currentTime - $invitedTime) <= $inviteTime) { //Done.
                            $this->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->formatMessage("$prefix §cInvite declined", true));
                            $this->getServer()->getPlayer($array["invitedby"])->sendMessage($this->formatMessage("$prefix §4$playerName §cdeclined the invitation"));
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cInvite has expired."));
                            $this->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                        }
                    }
                    /////////////////////////////// DELETE ///////////////////////////////
                    if(strtolower($args[0]) == "del" or strtolower($args[0]) == "disband"){
                        if ($this->isInFaction($playerName) == true) {
                            if ($this->isLeader($playerName)) {
                            }
                        }
                    }
				if($this->factionChatActive[$playerName]) {
                       	        unset($this->factionChatActive[$playerName]);
				}
				if($this->allyChatActive[$playerName]) {
                                unset($this->allyChatActive[$playerName]);
                                $faction = $this->getPlayerFaction($playerName);
                                $this->db->query("DELETE FROM plots WHERE faction='$faction';");
                                $this->db->query("DELETE FROM master WHERE faction='$faction';");
                                $this->db->query("DELETE FROM allies WHERE faction1='$faction';");
                                $this->db->query("DELETE FROM allies WHERE faction2='$faction';");
                                $this->db->query("DELETE FROM strength WHERE faction='$faction';");
                                $this->db->query("DELETE FROM motd WHERE faction='$faction';");
                                $this->db->query("DELETE FROM home WHERE faction='$faction';");
			        $this->db->query("DELETE FROM balance WHERE faction='$faction';");
		                $this->getServer()->broadcastMessage("§aThe player: §2$playerName §awho owned §3$faction §bhas been disbanded!");
                                $sender->sendMessage($this->formatMessage("$prefix §bThe Faction named: §a$faction §bhas been successfully disbanded and the faction plot, and Overclaims are unclaimed.", true));
                            } else {
                                $sender->sendMessage($this->formatMessage("$prefix §cYou are not leader!"));
				return true;
                            }
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou are not in a faction!"));
			    return true;
                        }
                    }
}
                    /////////////////////////////// LEAVE ///////////////////////////////
                    if(strtolower($args[0] == "leave" or strtolower($args[0] == "quit"))) {
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if ($this->isLeader($playerName) == false) {
			if ($this->factionChatActive[$playerName]) {
                        unset($this->factionChatActive[$playerName]);
			}
			if ($this->allyChatActive[$playerName]) {
                                unset($this->allyChatActive[$playerName]);
                            $faction = $this->getPlayerFaction($playerName);
                            $name = $sender->getName();
                            $this->db->query("DELETE FROM master WHERE player='$name';");
			    $this->getServer()->broadcastMessage("§2$name §bhas left §2$faction");
                            $sender->sendMessage($this->formatMessage("$prefix §bYou successfully left §a$faction", true));
                            $this->subtractFactionPower($faction, $this->getConfig()->get("PowerGainedPerPlayerInFaction"));
			    $this->takeFromBalance($faction, $this->getConfig()->get("MoneyGainedPerPlayerInFaction"));
                        } else {
                            $sender->sendMessage($this->getConfig()->formatMessage("$prefix §cYou must delete the faction or give\nleadership to someone else first"));
			    return true;
                        }
                    }
                    /////////////////////////////// SETHOME ///////////////////////////////
                    if(strtolower($args[0]) == "sethome" or strtolower($args[0]) == "shome"){
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to set home"));
                            return true;
                        }
			$faction_power = $this->getFactionPower($this->getPlayerFaction($playerName));
                        $needed_power = $this->getConfig()->get("PowerNeededToSetOrUpdateAHome");
                        if($faction_power < $needed_power){
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction doesn't have enough power to set a home."));
                            $sender->sendMessage($this->formatMessage("$prefix §4$needed_power §cpower is required to set a home. Your faction has §4$faction_power §cpower."));
			    return true;
			}
                        $factionName = $this->getPlayerFaction($sender->getName());
                        $stmt = $this->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z, world) VALUES (:faction, :x, :y, :z, :world);");
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":x", $sender->getX());
                        $stmt->bindValue(":y", $sender->getY());
                        $stmt->bindValue(":z", $sender->getZ());
			$stmt->bindValue(":world", $sender->getLevel()->getName());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->formatMessage("$prefix §bHome set succesfully for §a$factionName. §bNow, you can use: §3/f home", true));
                    }
                    /////////////////////////////// UNSETHOME ///////////////////////////////
                    if(strtolower($args[0]) == "unsethome" or strtolower($args[0]) == "delhome"){
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be leader to unset home"));
                            return true;
                        }
                        $faction = $this->getPlayerFaction($sender->getName());
                        $this->db->query("DELETE FROM home WHERE faction = '$faction';");
                        $sender->sendMessage($this->formatMessage("$prefix §bFaction Home was unset succesfully for §a$faction §3/f home §bwas removed from your faction.", true));
                    }
                    /////////////////////////////// HOME ///////////////////////////////
                    if (strtolower($args[0] == "home" or strtolower($args[0] == "base"))) {
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        			
		        }
                        $faction = $this->getPlayerFaction($sender->getName());
                        $result = $this->db->query("SELECT * FROM home WHERE faction = '$faction';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (!empty($array)) {
			        if ($array['world'] === null || $array['world'] === ""){
				                                $sender->sendMessage($this->formatMessage("$prefix §cHome is missing world name, please delete and make it again"));
				       			        return true;
			       				}
			       				if(Server::getInstance()->loadLevel($array['world']) === false){
+								$sender->sendMessage($this->formatMessage("$prefix The world '" . $array['world'] .  "'' could not be found"));
				       				return true;
			      				 }
                              				 $level = Server::getInstance()->getLevelByName($array['world']);
+                           $sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $level));
                            $sender->sendMessage($this->formatMessage("$prefix §bTeleported to your faction home succesfully!", true));
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cFaction Home is not set. You can set it with: §4/f sethome"));
                        }
                    }
		    /////////////////////////////// POWER ///////////////////////////////
                    if(strtolower($args[0]) == "power" or strtolower($args[0]) == "pw"){
                        if(!$this->isInFaction($playerName)) {
							$sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
			}
                        $faction_power = $this->getFactionPower($this->getPlayerFaction($sender->getName()));
                        
                        $sender->sendMessage($this->formatMessage("$prefix §bYour faction has§a $faction_power §bpower",true));
                    }
                    if(strtolower($args[0]) == "seepower" or strtolower($args[0]) == "sp"){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->formatMessage("$prefix §aPlease use: §b/f $args[0] <faction>\n§aDescription: §bAllows you to see A faction's power."));
                            return true;
                        }
                        if(!$this->factionExists($args[1])) {
							$sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoes not exist"));
                            return true;
			}
                        $faction_power = $this->getFactionPower($args[1]);
                        $sender->sendMessage($this->formatMessage("$prefix §a$args[1] §bhas §a$faction_power §bpower.",true));
                    }
                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if (strtolower($args[0] == "ourmembers" or strtolower($args[0] == "members"))) {
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        $this->getPlayersInFactionByRank($sender, $this->getPlayerFaction($playerName), "Member");
                    }
                    if (strtolower($args[0] == "listmembers" or strtolower($args[0] == "lm"))) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction>\n§aDescription: §dGet's a list of faction members in a faction."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist"));
                            return true;
                        }
                        $this->getPlayersInFactionByRank($sender, $args[1], "Member");
                    }
                    if (strtolower($args[0] == "ourofficers" or strtolower($args[0] == "officers"))) {
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        $this->getPlayersInFactionByRank($sender, $this->getPlayerFaction($playerName), "Officer");
                    }
                    if (strtolower($args[0] == "listofficers" or strtolower($args[0] == "lo"))) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction>\n§aDescription: §dGet's a list of officers in a faction."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist"));
                            return true;
                        }
                        $this->getPlayersInFactionByRank($sender, $args[1], "Officer");
                    }
                    if (strtolower($args[0] == "ourleader" or strtolower($args[0] == "ourl"))) {
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        $this->getPlayersInFactionByRank($sender, $this->getPlayerFaction($playerName), "Leader");
                    }
                    if (strtolower($args[0] == "listleader" or strtolower($args[0] == "ll"))) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction>\n§aDescription: §dGet's the name of the leader of a faction."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist"));
                            return true;
                        }
                        $this->getPlayersInFactionByRank($sender, $args[1], "Leader");
                    }
                    if (strtolower($args[0] == "say" or strtolower($args[0] == "bc"))) {
			if (!$this->getConfig()->get("AllowChat")) {
			    $sender->sendMessage($this->formatMessage("§c/f $args[0] is disabled"));
			    return true;
			}
			if (!($this->isInFaction($playerName))) {
			    $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to send faction messages"));
			    return true;
			}
			$r = count($args);
			$row = array();
			$rank = "";
			$f = $this->getPlayerFaction($playerName);
			    
			if ($this->isOfficer($playerName)) {
			    $rank = "*";
			} else if ($this->isLeader($playerName)) {
			    $rank = "**";
			}
			$message = "-> ";
			for ($i = 0; $i < $r - 1; $i = $i + 1) {
			    $message = $message . $args[$i + 1] . " ";
			}
			$result = $this->db->query("SELECT * FROM master WHERE faction='$f';");
			for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
			    $row[$i]['player'] = $resultArr['player'];
			    $p = $this->getServer()->getPlayerExact($row[$i]['player']);
			    if ($p instanceof Player) {
				$p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<FM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$playerName> " . ": " . TextFormat::RESET);
				$p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
			    }
			}
		    }
                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
                    if (strtolower($args[0] == "enemy" or strtolower($args[0] == "e"))) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction>\n§aDescription: §dEnemy a faction."));
                            return true;
                        }
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be the leader to do this"));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist"));
                            return true;
                        }
                        if ($this->getPlayerFaction($playerName) == $args[1]) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction can not enemy with itself"));
                            return true;
                        }
                        if ($this->areAllies($this->getPlayerFaction($playerName), $args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction is already enemied with §4$args[1]"));
                            return true;
                        }
                        $fac = $this->getPlayerFaction($playerName);
                        $leader = $this->getServer()->getPlayer($this->getLeader($args[1]));
                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe leader of the faction named §4$args[1] §cis not online"));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->formatMessage("$prefix §bYou are now enemies with §a$args[1]!", true));
                        $leader->sendMessage($this->formatMessage("$prefix §bThe leader of §a$fac §bhas declared your faction as an enemy", true));
                    }
                    if(strtolower($args[0]) == "ally" or strtolower($args[0]) == "a"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction>\n§aDescription: §dAlly with a faction."));
                            return true;
                        }
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be the leader to do this"));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist"));
                            return true;
                        }
                        if ($this->getPlayerFaction($playerName) == $args[1]) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction can not ally with itself"));
                            return true;
                        }
                        if ($this->areAllies($this->getPlayerFaction($playerName), $args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction is already allied with §4$args[1]"));
                            return true;
                        }
                        $fac = $this->getPlayerFaction($playerName);
                        $leader = $this->getServer()->getPlayer($this->getLeader($args[1]));
                        $this->updateAllies($fac);
                        $this->updateAllies($args[1]);
                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe leader of the faction named §4$args[1] §cis not online"));
                            return true;
                        }
                        if ($this->getAlliesCount($args[1]) >= $this->getAlliesLimit()) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §chas the maximum amount of allies", true));
             
                        }
                        if ($this->getAlliesCount($fac) >= $this->getAlliesLimit()) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction has the maximum amount of allies", true));
                        }
                        $stmt = $this->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                        $stmt->bindValue(":player", $leader->getName());
                        $stmt->bindValue(":faction", $args[1]);
                        $stmt->bindValue(":requestedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->formatMessage("$prefix §bYou requested to ally with §a$args[1]!\n§bWait for the leader's response...", true));
                        $leader->sendMessage($this->formatMessage("$prefix §bThe leader of §a$fac §brequested an alliance.\nType §3/f allyok §bto accept or §3/f allyno §bto deny.", true));
                    }
                    if(strtolower($args[0]) == "unally" or strtolower($args[0]) == "una"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction>\n§aDescription: §dUn allies a faction."));
                            return true;
                        }
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be the leader to do this"));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist"));
                            return true;
                        }
                        if ($this->getPlayerFaction($playerName) == $args[1]) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction can not break alliance with itself"));
                            return true;
                        }
                        if (!$this->areAllies($this->getPlayerFaction($playerName), $args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction is already allied with §4$args[1]"));
                            return true;
                        }
                        $fac = $this->getPlayerFaction($playerName);
                        $leader = $this->getServer()->getPlayer($this->getLeader($args[1]));
                        $this->deleteAllies($fac, $args[1]);
                        $this->deleteAllies($args[1], $fac);
                        $this->subtractFactionPower($fac, $this->getConfig()->get("PowerGainedPerAlly"));
                        $this->subtractFactionPower($args[1], $this->getConfig()->get("PowerGainedPerAlly"));
			$this->takeFromBalance($fac, $this->getConfig()->get("MoneyGainedPerAlly"));
                        $this->updateAllies($fac);
                        $this->updateAllies($args[1]);
                        $sender->sendMessage($this->formatMessage("$prefix §bYour faction §a$fac §bis no longer allied with §a$args[1]", true));
                        if ($leader instanceof Player) {
                            $leader->sendMessage($this->formatMessage("$prefix §bThe leader of §a$fac §bbroke the alliance with your faction §a$args[1]", false));
                        }
                    }
                    if(strtolower($args[0]) == "forceunclaim" or strtolower($args[0]) == "func"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <faction>\n§aDescription: §dForce Unclaims a land. - Operators only."));
                            return true;
                        }
                        if (!$this->factionExists($args[1])) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist"));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->formatMessage("$prefix §4§lYou must be OP to do this."));
                            return true;
                        }
                        $sender->sendMessage($this->formatMessage("$prefix §bSuccessfully unclaimed the unwanted plot of §a$args[1]"));
                        $this->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                    }
                    if (strtolower($args[0] == "allies" or strtolower($args[0] == "ourallies"))) {
                        if (!isset($args[1])) {
                            if (!$this->isInFaction($playerName)) {
                                $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                                return true;
                            }
                            $this->updateAllies($this->getPlayerFaction($playerName));
                            $this->getAllAllies($sender, $this->getPlayerFaction($playerName));
                        } else {
                            if (!$this->factionExists($args[1])) {
                                $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoesn't exist"));
                                return true;
                            }
                            $this->updateAllies($args[1]);
                            $this->getAllAllies($sender, $args[1]);
                        }
                    }
                    if(strtolower($args[0]) == "allyok" or strtolower($args[0]) == "allyaccept"){
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = strtolower($playerName);
                        $result = $this->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction has not been requested to ally with any factions"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
			$allyTimes = $this->getConfig()->get("AllyTimes");
                        if (($currentTime - $allyTime) <= $allyTimes) { //Done.
                            $requested_fac = $this->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->getPlayerFaction($playerName);
                            $this->setAllies($requested_fac, $sender_fac);
                            $this->setAllies($sender_fac, $requested_fac);
                            $this->addFactionPower($sender_fac, $this->getConfig()->get("PowerGainedPerAlly"));
                            $this->addFactionPower($requested_fac, $this->getConfig()->get("PowerGainedPerAlly"));
			    $this->addToBalance($sender_fac, $this->getConfig()->get("MoneyGainedPerAlly"));
			    $this->addToBalance($requested_fac, $this->getConfig()->prefs->get("MoneyGainedPerAlly"));
                            $this->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $this->updateAllies($requested_fac);
                            $this->updateAllies($sender_fac);
                            $sender->sendMessage($this->formatMessage("$prefix §bYour faction has successfully allied with §a$requested_fac", true));
                            $this->getServer()->getPlayer($array["requestedby"])->sendMessage($this->formatMessage("$prefix §a$playerName §bfrom §a$sender_fac §bhas accepted the alliance!", true));
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cRequest has timed out"));
                            $this->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                        }
                    }
                    if(strtolower($args[0]) == "allyno" or strtolower($args[0]) == "allydeny"){
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->isLeader($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = strtolower($playerName);
                        $result = $this->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYour faction has not been requested to ally with any factions"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
			$allyTimes = $this->getConfig()->get("AllyTimes");
                        if (($currentTime - $allyTime) <= $allyTime) { //Done
                            $requested_fac = $this->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->getPlayerFaction($playerName);
                            $this->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->formatMessage("$prefix §bYour faction has successfully declined the alliance request.", true));
                            $this->getServer()->getPlayer($array["requestedby"])->sendMessage($this->formatMessage("$prefix §a$playerName §bfrom §a$sender_fac §bhas declined the alliance!"));
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cRequest has timed out"));
                            $this->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                        }
                    }
                    /////////////////////////////// ABOUT ///////////////////////////////
                    if(strtolower($args[0]) == "about" or strtolower($args[0]) == "info"){
                        $sender->sendMessage(TextFormat::GREEN . "§7[§6Void§bFactions§cPE§dINFO§7]");
                        $sender->sendMessage(TextFormat::GOLD . "§7[§2MODDED§7] §3This version is based from §6Void§bFactions§cPE");
			$sender->sendMessage(TextFormat::GREEN . "§bPlugin Information:");
			$sender->sendMessage(TextFormat::GREEN . "§aFaction Build release: §5463");
			$sender->sendMessage(TextFormat::GREEN . "§aBuild Tested and works on: §4426-438");
			$sender->sendMessage(TextFormat::GREEN . "§aPlugin Link: §5Not showing due to self-leak information");
			$sender->sendMessage(TextFormat::GREEN . "§aPlugin download: §5Not showing due to self-leak information.");
			$sender->sendMessage(TextFormat::GREEN . "§aAuthor: §5VMPE Development Team");
			$sender->sendMessage(TextFormat::GREEN . "§aOriginal Author: §5Tethered");
			$sender->sendMessage(TextFormat::GREEN . "§aDescription: §5A factions plugin which came back to life and re-added features like the good 'ol' versions of FactionsPro.");
			$sender->sendMessage(TextFormat::GREEN . "§aVersion: §5v2.0.0-implement-1");
			$sender->sendMessage(TextFormat::GREEN . "§aPlugin Version: §5v3.0.0-DEVc");
			$sender->sendMessage(TextFormat::GREEN . "§aSupported PMMP API's: §53.0.0-ALPHA10 - ALPHA15.");
                    }
                    ////////////////////////////// CHAT ////////////////////////////////
		    
                    if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {
                        if (!$this->getConfig()->get("AllowChat")){
                            $sender->sendMessage($this->formatMessage("$prefix §6All Faction chat is disabled", false));
                        }
                        
                        if ($this->isInFaction($playerName)) {
                            if (isset($this->factionChatActive[$playerName])) {
                                unset($this->factionChatActive[$playerName]);
                                $sender->sendMessage($this->formatMessage("$prefix §6Faction chat disabled", true));
                            } else {
                                $this->factionChatActive[$playerName] = 1;
                                $sender->sendMessage($this->formatMessage("$prefix §aFaction chat enabled", true));
                            }
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou are not in a faction"));
                            return true;
                        }
                    }
                    if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {
                        if (!$this->getConfig()->get("AllowChat")){
                            $sender->sendMessage($this->formatMessage("$prefix §cAll Faction chat is disabled", false));
                        }
                        
                        if ($this->isInFaction($playerName)) {
                            if (isset($this->allyChatActive[$playerName])) {
                                unset($this->allyChatActive[$playerName]);
                                $sender->sendMessage($this->formatMessage("$prefix §6Ally chat disabled", true));
                            } else {
                                $this->allyChatActive[$playerName] = 1;
                                $sender->sendMessage($this->formatMessage("$prefix §aAlly chat enabled", true));
                            }
                        } else {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou are not in a faction"));
                            return true;
                        }
                    }
		////////////////////////////// BALANCE, by primus ;) ///////////////////////////////////////
					if(strtolower($args[0]) == "bal" or strtolower($args[0]) == "balance"){
						if(!$this->isInFaction($playerName)){
							$sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to check balance!", false));
							return true;
						}
						$faction = $this->getPlayerFaction($playerName);
						$balance = $this->getBalance($faction);
						$sender->sendMessage($this->formatMessage("$prefix §6Faction balance: " . TextFormat::GREEN . "$".$balance));
						return true;
					}
		    			if(strtolower($args[0]) == "seebalance" or strtolower($args[0]) == "sb"){
                        		   if(!isset($args[1])){
                            		        $sender->sendMessage($this->formatMessage("$prefix §aPlease use: §b/f $args[0] <faction>\n§aDescription: §bAllows you to see A faction's balance."));
                           			return true;
                        		   }
                        		   if(!$this->factionExists($args[1])) {
									   $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoes not exist"));
                            		       return true;
					   }
                       			   $balance = $this->getBalance($args[1]);
                       			   $sender->sendMessage($this->formatMessage("$prefix §bThe faction §a $args[1] §bhas §a$balance §bMoney", true));
                    			}
					if(strtolower($args[0]) == "withdraw" or strtolower($args[0]) == "wd"){
					   if(!isset($args[1])){
							$sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <amount>\n§aDescription: §dWithdraw money from your faction bank."));
							return true;
                                                }
                        if(($e = $this->getEconomy()) == null){
						}
						if(!is_numeric($args[1])){
							$sender->sendMessage($this->formatMessage("$prefix §cAmount must be numeric value. You put §4$args[1]", false));
							return true;
						}
						if(!$this->isInFaction($playerName)){
							$sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to check balance!", false));
							return true;
						}
						if(!$this->isLeader($playerName)){
							$sender->sendMessage($this->formatMessage("$prefix §cOnly leader can withdraw from faction bank account!", false));
							return true;
						}
						$faction = $this->getPlayerFaction($sender->getName());
						if( (($fM = $this->getBalance($faction)) - ($args[1]) ) < 0 ){
							$sender->sendMessage($this->formatMessage("$prefix §cYour faction doesn't have enough money! It has: §4$fM", false));
							return true;
						}
						$this->takeFromBalance($faction, $args[1]);
						$e->addMoney($sender, $args[1], false, "faction bank account");
						$sender->sendMessage($this->formatMessage("$prefix §a$".$args[1]." §bgranted from faction", true));
						return true;
					}
					if(strtolower($args[0]) == "donate" or strtolower($args[0]) == "pay"){
					   if(!isset($args[1])){
						       $sender->sendMessage($this->formatMessage("$prefix §bPlease use: §3/f $args[0] <amount>\n§aDescription: §dDonate money to your/the faction you're in."));
						       return true;
                                                }
                        if(($e = $this->getEconomy()) === null){
						}
						if(!is_numeric($args[1])){
							$sender->sendMessage($this->formatMessage("$prefix §cAmount must be numeric value. You put: §4$args[1]", false));
							return true;
						}
						if(!$this->isInFaction($playerName)){
							$sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to donate", false));
							return true;
						}
						if( ( ($e->myMoney($sender)) - ($args[1]) ) < 0 ){
							$sender->sendMessage($this->formatMessage("$prefix §cYou dont have enough money!", false));
							return true;
						}
						$faction = $this->getPlayerFaction($sender->getName());
						if($e->reduceMoney($sender, $args[1], false, "faction bank account") === \onebone\economyapi\EconomyAPI::RET_SUCCESS){
							$this->addToBalance($faction, $args[1]);
							$sender->sendMessage($this->formatMessage("$prefix §a$".$args[1]." §bdonated to your faction"));
							return true;
						}
					}
                /////////////////////////////// MAP, map by Primus (no compass) ////////////////////////////////
					// Coupon for compass: G1wEmEde0mp455
					if(strtolower($args[0] == "map" or strtolower($args[0] == "compass"))) {
                        if(!isset($args[1])) {
					    $size = 1;
						$map = $this->getMap($sender, self::MAP_WIDTH, self::MAP_HEIGHT, $sender->getYaw(), $size);
						foreach($map as $line) {
				        $sender->sendMessage($line);
                          
						}
						return true;
					    }
                    }
               
                /////////////////////////////// WHO ///////////////////////////////
                if (strtolower($args[0]) == "who" or strtolower($args[0]) == "facinfo") {
                    if (isset($args[1])) {
                        if (!(ctype_alnum($args[1])) or !($this->factionExists($args[1]))) {
                            $sender->sendMessage($this->formatMessage("$prefix §cThe faction named §4$args[1] §cdoes not exist"));
                            return true;
                        }
                        $faction = $args[1];
                        $result = $this->db->query("SELECT * FROM motd WHERE faction='$faction';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        $power = $this->getFactionPower($faction);
                        $message = $array["message"];
                        $leader = $this->getLeader($faction);
                        $numPlayers = $this->getNumberOfPlayers($faction);
			$maxPlayers = $this->getConfig()->get("MaxPlayersPerFaction");
			$balance = $this->getBalance($faction);
			$factioninfo = $this->getConfig()->get("FactionInfo");
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::RESET . "$factioninfo" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§cLeader Name: " . TextFormat::YELLOW . "§5$leader" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§dPlayers: " . TextFormat::LIGHT_PURPLE . "§5$numPlayers/$maxPlayers" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§eStrength " . TextFormat::RED . "§d$power" . " §5STR" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§aDescription: " . TextFormat::AQUA . TextFormat::UNDERLINE . "§5$message" . TextFormat::RESET);
			$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§bFaction Balance: " . TextFormat::AQUA . "§5$" . TextFormat::DARK_PURPLE . "$balance" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::RESET . "$factioninfo" . TextFormat::RESET);
		    } else {
                        if (!$this->isInFaction($playerName)) {
                            $sender->sendMessage($this->formatMessage("$prefix §cYou must be in a faction to use this!"));
                            return true;
                        }
                        $faction = $this->getPlayerFaction(($sender->getName()));
                        $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        $power = $this->getFactionPower($faction);
                        $message = $array["message"];
                        $leader = $this->getLeader($faction);
                        $numPlayers = $this->getNumberOfPlayers($faction);
			$maxPlayers = $this->getConfig()->get("MaxPlayersPerFaction");
			$balance = $this->getBalance($faction);
			$myfacmessage = $this->getConfig()->get("MyFactionMessage");
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::RESET . "$myfacmessage" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§bFaction Name: " . TextFormat::GREEN . "§5$faction" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§cLeader Name: " . TextFormat::YELLOW . "§5$leader" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§dPlayers: " . TextFormat::LIGHT_PURPLE . "§5$numPlayers/$maxPlayers" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§eStrength: " . TextFormat::RED . "§d$power" . " §5STR" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§aDescription: " . TextFormat::AQUA . TextFormat::UNDERLINE . "§b$message" . TextFormat::RESET);
			$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§bFaction Balance: " . TextFormat::AQUA . "§5$" . TextFormat::DARK_PURPLE . "$balance" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::RESET . "$myfacmessage" . TextFormat::RESET);
                    }
                    return true;
                }
		if(strtolower($args[0]) == "help" or strtolower($args[0]) == "?"){
			if(!isset($args[1])) {
			   $sender->sendMessage(TextFormat::BLUE . "$prefix §aPlease use §b/f help <page> §afor a list of pages. (1-7]");
			   	return true;
			}
			$serverName = $this->getConfig()->get("ServerName");
			if($args[1] == 1){
				$sender->sendMessage(TextFormat::BLUE . "$serverName §dHelp §2[§51/7§2]");
				$sender->sendMessage(TextFormat::RED . "§a/f about|info - §7Shows Plugin information");
				$sender->sendMessage(TextFormat::GREEN . "§a/f accept|yes - §7Accepts an faction invitation");
				$sender->sendMessage(TextFormat::GREEN . "§a/f claim|cl - §7Claims a faction plot!");
				$sender->sendMessage(TextFormat::GREEN . "§a/f create|make <name> - §7Creates a faction.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f del|disband - Deletes a faction.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f demote|dm2 <player> - §7Demotes a player from a faction.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f deny|no - §7Denies a player's invitation.");
				return true;
			}
			$serverName = $this->getConfig()->get("ServerName");
			if($args[1] == 2){
				$sender->sendMessage(TextFormat::BLUE . "$serverName §dHelp §2[§52/7§2]");
				$sender->sendMessage(TextFormat::RED . "§a/f home|base - §7Teleports to your faction home.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f help <page> - §7Factions help.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f who|facinfo - §7Your Faction info.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f who|facinfo <faction> - §7Other faction info.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f invite|inv <player> - §7Invite a player to your faction.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f kick|k <player> - §7Kicks a player from your faction.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f leader|transferleader <player> - §7Transfers leadership.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f leave|quit - §7Leaves a faction.");
				return true;
			}
			$serverName = $this->getConfig()->get("ServerName");
			if($args[1] == 3){
				$sender->sendMessage(TextFormat::BLUE . "$serverName §dHelp §2[§53/7§2]");
				$sender->sendMessage(TextFormat::GREEN . "§a/f motd|desc - §7Set your faction Message of the day.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f promote|pm2 <player> - §7Promote a player.");
				$sender->sendMessage(TextFormat::GREEN . "§a/f sethome|shome - §7Set a faction home.\n§a/f unclaim|uncl - §7Unclaims a faction plot.\n§a/f unsethome|delhome - §7Deletes a faction home.\n§a/f top|lb - §7Checks top 10 BEST Factions on the server.\n§a/f war|wr <factionname|tp> - §7Starts a faction war / Requests a faction war.");
				return true;
			}
			$serverName = $this->getConfig()->get("ServerName");
			if($args[1] == 4){
				$sender->sendMessage(TextFormat::BLUE . "$serverName §dHelp §2[§54/7§2]");
				$sender->sendMessage(TextFormat::RED . "§a/f enemy|e <faction> - §7Enemy with a faction");
				$sender->sendMessage(TextFormat::RED . "§a/f ally|a <faction> - §7Ally a faction.");
				$sender->sendMessage(TextFormat::RED . "§a/f allyok|allyaccept - §7Accepts a ally request.");
				$sender->sendMessage(TextFormat::RED . "§a/f allydeny|no - §7Denies a ally request.");
				$sender->sendMessage(TextFormat::RED . "§a/f unally|una - §7Un allies with a faction.");
				$sender->sendMessage(TextFormat::RED . "§a/f allies|ourallies - §7Checks a list of allies you currently have.");
				$sender->sendMessage(TextFormat::RED . "§a/f say|bc <MESSAGE> - §7Broadcast a faction measage.");
				return true;
			}
			$serverName = $this->getConfig()->get("ServerName");
			if($args[1] == 5){
				$sender->sendMessage(TextFormat::BLUE . "$serverName §dHelp §2[§55/7§2]");
				$sender->sendMessage(TextFormat::RED . "§a/f chat|c - §7Toggles faction chat.");
				$sender->sendMessage(TextFormat::RED . "§a/f allychat|ac - §7Toggles Ally chat.");
				$sender->sendMessage(TextFormat::RED . "§a/f plotinfo|pinfo - §7Checks if a specific area is claimed or not.");
				$sender->sendMessage(TextFormat::RED . "§a/f power|pw - §7Checks to see how much power you have.");
				$sender->sendMessage(TextFormat::RED . "§a/f seepower|sp <faction> - §7Sees power of another faction.");
				return true;
			}
			$serverName = $this->getConfig()->get("ServerName");
			if($args[1] == 6){
				$sender->sendMessage(TextFormat::BLUE . "$serverName §dHelp §2[§56/7§2]");
				$sender->sendMessage(TextFormat::RED . "§a/f listleader|ll <faction> - §7Checks who the leader is in a faction.");
				$sender->sendMessage(TextFormat::RED . "§a/f listmembers|lm <faction> - §7Checks who the members are in a faction.");
				$sender->sendMessage(TextFormat::RED . "§a/f listofficers|lo <faction> - §7Checks who the officers are in a faction.");
				$sender->sendMessage(TextFormat::RED . "§a/f ourmembers|members - §7Checks who your faction members are.");
				$sender->sendMessage(TextFormat::RED . "§a/f ourofficers|officers - §7Checks who your faction officers are.");
				$sender->sendMessage(TextFormat::RED . "§a/f ourleader|leaders - §7Checks to see who your leader is.");
				return true;
                        }
			$serverName = $this->getConfig()->get("ServerName");
			if($args[1] == 7){
				$sender->sendMessage(TextFormat::BLUE . "$serverName §dHelp §2[§57/7§2]");
				$sender->sendMessage(TextFormat::RED . "§a/f donate|pay <amount> - §7Donate to a faction from your Eco Bank.");
				$sender->sendMessage(TextFormat::RED . "§a/f withdraw|wd <amount> - §7With draw from your faction bank");
				$sender->sendMessage(TextFormat::RED . "§a/f balance|bal - §7Checks your faction balance");
				$sender->sendMessage(TextFormat::RED . "§a/f map|compass - §7Faction Map command");
				$sender->sendMessage(TextFormat::RED . "§a/f overclaim|oc - §7Overclaims a plot.");
				$sender->sendMessage(TextFormat::RED . "§a/f seebalance|sb - §7Checks other faction balances.");
			}
			if($sender->isOp()){
				$sender->sendMessage(TextFormat::RED . "§4§lUse /f help 8 to see OP Commands.");
				return true;
			}
			$serverName = $this->getConfig()->get("ServerName");
			if($args[1] == 8){    
				$sender->sendMessage(TextFormat::BLUE . "$serverName §dHelp (OP Commands) §2[§51/1§2]");
				$sender->sendMessage(TextFormat::RED. "§4/f addstrto|addpower <faction> <STR> - §cAdds Strength to a faction.");
				$sender->sendMessage(TextFormat::RED . "§4/f addbalto|addmoney <faction> <money> - §cAdds Money to a faction.");
				$sender->sendMessage(TextFormat::RED . "§4/f forcedelete|fdisband <faction> - §cForce deletes a faction.");
				$sender->sendMessage(TextFormat::RED . "§4/f forceunclaim|func <faction> - §cForce unclaims a plot / land.");
				$sender->sendMessage(TextFormat::RED . "§4/f rmbalto|rmmoney <faction> <money> - §cForcefully removes the money from a faction.");
				$sender->sendMessage(TextFormat::RED . "§4/f rmstrto|rmpower <faction> <str> - §cForcefully removes the STR from a faction.");
				return true;
			}
		   }
       } else {
	    $prefix = $this->getConfig()->get("pluginprefix");
            $this->getServer()->getLogger()->info($this->formatMessage($this->getConfig()->get("pluginprefix "). $this->getConfig()->get("consolemessage")));
       }
        return true;
    }
    public function alphanum($string){
        if(function_exists('ctype_alnum')){
            $return = ctype_alnum($string);
        }else{
            $return = preg_match('/^[a-z0-9]+$/i', $string) > 0;
        }
        return $return;
    }
    public function getMap(Player $observer, int $width, int $height, int $inDegrees, int $size) { // No compass
		$to = (int)sqrt($size);
		$centerPs = new Vector3($observer->x >> $to, 0, $observer->z >> $to);
		$map = [];
		$centerFaction = $this->plugin->factionFromPoint($observer->getFloorX(), $observer->getFloorZ());
		$centerFaction = $centerFaction ? $centerFaction : "Wilderness";
		$head = TextFormat::DARK_GREEN . "§3________________." . TextFormat::DARK_GRAY . "[" .TextFormat::GREEN . " (" . $centerPs->getX() . "," . $centerPs->getZ() . ") " . $centerFaction . TextFormat::DARK_GRAY . "]" . TextFormat::DARK_GREEN . "§3.________________";
		$map[] = $head;
		$halfWidth = $width / 2;
		$halfHeight = $height / 2;
		$width = $halfWidth * 2 + 1;
		$height = $halfHeight * 2 + 1;
		$topLeftPs = new Vector3($centerPs->x + -$halfWidth, 0, $centerPs->z + -$halfHeight);
		// Get the compass
		$asciiCompass = self::getASCIICompass($inDegrees, TextFormat::RED, TextFormat::GOLD);
		// Make room for the list of names
		$height--;
		/** @var string[] $fList */
		$fList = array();
		$chrIdx = 0;
		$overflown = false;
		$chars = "-";
		// For each row
		for ($dz = 0; $dz < $height; $dz++) {
			// Draw and add that row
			$row = "";
			for ($dx = 0; $dx < $width; $dx++) {
				if ($dx == $halfWidth && $dz == $halfHeight) {
					$row .= "§b". "-";
					continue;
				}
				if (!$overflown && $chrIdx >= strlen($this->getMapBlock())) $overflown = true;
				$herePs = $topLeftPs->add($dx, 0, $dz);
				$hereFaction = $this->factionFromPoint($herePs->x << $to, $herePs->z << $to);
				$contains = in_array($hereFaction, $fList, true);
				if ($hereFaction === NULL) {
                    $SemClaim = "§7". "-";
					$row .= $SemClaim;
				} elseif (!$contains && $overflown) {
                    $Caverna = "§f"."-";
					$row .= $Caverna;
				} else {
					if (!$contains) $fList[$chars{$chrIdx++}] = $hereFaction;
					$fchar = "-";
					$row .= $this->getColorForTo($observer, $hereFaction) . $fchar;
				}
			}
			$line = $row; // ... ---------------
			// Add the compass
          $OPlayer = "§b". "-";
			if ($dz == 0) $line = substr($row, 0 * strlen($OPlayer))."  ".$asciiCompass[0];
			if ($dz == 1) $line = substr($row, 0 * strlen($OPlayer))."  ".$asciiCompass[1];
			if ($dz == 2) $line = substr($row, 0 * strlen($OPlayer))."  ". $asciiCompass[2];
          if ($dz == 4) $line = substr($row, 0 * strlen($OPlayer))."  §2". "-" . " §a Wilderness";
          if ($dz == 5) $line = substr($row, 0 * strlen($OPlayer)). "  §3". "-" . " §b Claimed Land";
         if ($dz == 6) $line = substr($row, 0 * strlen($OPlayer)). "  §4". "-" ." §c Warzone";
         if ($dz == 7) $line = substr($row, 0 * strlen($OPlayer)). "  §5". "-" ." §d You";
         if ($dz == 8) $line = substr($row, 0 * strlen($OPlayer));
         
			$map[] = $line;
		}
		$fRow = "";
		foreach ($fList as $char => $faction) {
			$fRow .= $this->getColorForTo($observer, $faction) . $this->getMapBlock() . ": " . $faction . " ";
		}
        if ($overflown) $fRow .= self::MAP_OVERFLOW_MESSAGE;
		$fRow = trim($fRow);
		$map[] = $fRow;
		return $map;
	}
	public function getColorForTo(Player $player, $faction) {
		if($this->getPlayerFaction($player->getName()) === $faction) {
			return "§6";
		}
		return "§c";
	}
	   const N = 'N';
    const NE = '/';
    const E = 'E';
    const SE = '\\';
    const S = 'S';
    const SW = '/';
    const W = 'W';
    const NW = '\\';
    public static function getASCIICompass($degrees, $colorActive, $colorDefault) : array
    {
        $ret = [];
        $point = self::getCompassPointForDirection($degrees);
        $row = "";
        $row .= ($point === self::NW ? $colorActive : $colorDefault) . self::NW;
        $row .= ($point === self::N ? $colorActive : $colorDefault) . self::N;
        $row .= ($point === self::NE ? $colorActive : $colorDefault) . self::NE;
        $ret[] = $row;
        $row = "";
        $row .= ($point === self::W ? $colorActive : $colorDefault) . self::W;
        $row .= $colorDefault . "+";
        $row .= ($point === self::E ? $colorActive : $colorDefault) . self::E;
        $ret[] = $row;
        $row = "";
        $row .= ($point === self::SW ? $colorActive : $colorDefault) . self::SW;
        $row .= ($point === self::S ? $colorActive : $colorDefault) . self::S;
        $row .= ($point === self::SE ? $colorActive : $colorDefault) . self::SE;
        $ret[] = $row;
        return $ret;
    }
    public static function getCompassPointForDirection($degrees)
    {
        $degrees = ($degrees - 180) % 360;
        if ($degrees < 0)
            $degrees += 360;
        if (0 <= $degrees && $degrees < 22.5)
            return self::N;
        elseif (22.5 <= $degrees && $degrees < 67.5)
            return self::NE;
        elseif (67.5 <= $degrees && $degrees < 112.5)
            return self::E;
        elseif (112.5 <= $degrees && $degrees < 157.5)
            return self::SE;
        elseif (157.5 <= $degrees && $degrees < 202.5)
            return self::S;
        elseif (202.5 <= $degrees && $degrees < 247.5)
            return self::SW;
        elseif (247.5 <= $degrees && $degrees < 292.5)
            return self::W;
        elseif (292.5 <= $degrees && $degrees < 337.5)
            return self::NW;
        elseif (337.5 <= $degrees && $degrees < 360.0)
            return self::N;
        else
            return null;
    }
    public function onDisable(): void {
        $this->db->close();
    }
}
