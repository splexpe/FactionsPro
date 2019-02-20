<?php
namespace FactionsPro;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginDescription;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\block\Snow;
use pocketmine\math\Vector3;
use onebone\economyapi\EconomyAPI;
use FactionsPro\tasks\updateTagTask;
class FactionMain extends PluginBase implements Listener {
	
    public $db;
    public $prefs;
    public $war_req = [];
    public $wars = [];
    public $war_players = [];
    public $antispam;
    public $purechat;
    public $economyapi;
    public $factionChatActive = [];
    public $allyChatActive = [];
	 private $prefix = "§a[§7Splex§3PE§a]";
	  const HEX_SYMBOL = "e29688";
	
    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        if (!file_exists($this->getDataFolder() . "BannedNames.txt")) {
            $file = fopen($this->getDataFolder() . "BannedNames.txt", "w");
            $txt = "Admin:admin:Staff:staff:Owner:owner:Builder:builder:Op:OP:op:':/:^:[:-:+:]";
            fwrite($file, $txt);
        }
        Server::getInstance()->getPluginManager()->registerEvents(new FactionListener($this), $this);
        $this->antispam = Server::getInstance()->getPluginManager()->getPlugin("AntiSpamPro");
        if (!$this->antispam) {
            $this->getLogger()->info("Add AntiSpamPro to ban rude Faction names");
        }
        $this->purechat = Server::getInstance()->getPluginManager()->getPlugin("PureChat");
        if (!$this->purechat) {
            $this->getLogger()->info("Add PureChat to display Faction ranks in chat");
        }
		$this->economyapi = Server::getInstance()->getPluginManager()->getPlugin("EconomyAPI");
		if (!$this->economyapi) {
	        $this->getLogger()->info("Add EconomyAPI to use the f value system.");
		}
        $this->fCommand = new FactionCommands($this);
         $this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
            "MaxFactionNameLength" => 15,
            "MaxPlayersPerFaction" => 30,
            "OnlyLeadersAndOfficersCanInvite" => true,
            "OfficersCanClaim" => false,
	    "ClaimingEnabled" => true,
            "PlotSize" => 16,
            "PlayersNeededInFactionToClaimAPlot" => 5,
            "PowerNeededToClaimAPlot" => 1000,
            "PowerNeededToSetOrUpdateAHome" => 250,
            "PowerGainedPerPlayerInFaction" => 50,
            "PowerGainedPerKillingAnEnemy" => 10,
            "PowerGainedPerAlly" => 100,
            "AllyLimitPerFaction" => 5,
            "enable-faction-tag" => true,
            "enable-not-created-hud" => true,
            "hud-message" => "&cYou have not created a faction yet. &aUse: &b/f create <name>!",
	    "updateTag-tick" => 20,
            "faction-tag" => "§3{player} §5| §3{faction}",
            "tag-type" => "scoretag", //Options: scoretag, Display tag, or nametag!
            "update-checker" => true,
            "TheDefaultPowerEveryFactionStartsWith" => 0,
	    "EnableOverClaim" => true,
            "ClaimWorlds" => [],
            "AllowChat" => true,
            "AllowFactionPvp" => false,
            "AllowAlliedPvp" => false,
            "defaultFactionBalance" => 0,
	    "MoneyGainedPerPlayerInFaction" => 20,
	    "MoneyGainedPerAlly" => 50,
            "MoneyNeededToClaimAPlot" => 0,
	    "ServerName" => "§6Void§bFactions§cPE",
                "prefix" => "§7[§6Void§bFactions§cPE§7]",
                "spawnerPrices" => [
                	"skeleton" => 500,
                	"pig" => 200,
                	"chicken" => 100,
                	"iron golem" => 5000,
                	"zombie" => 800,
                	"creeper" => 4000,
                	"cow" => 700,
                	"spider" => 500,
                	"magma" => 10000,
                	"ghast" => 10000,
                	"blaze" => 15000,
			"empty" => 100
                ],
		));
	    $this->checkUpdate();
			if($this->prefs->get("enable-faction-tag") == "true"){
		 $this->getScheduler()->scheduleRepeatingTask(new updateTagTask($this), $this->prefs->get("updateTag-tick"));
				$this->tagCheck();
$this->prefix = $this->prefs->get("prefix", $this->prefix);
		$this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT, world TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT, world VARCHAR);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS balance(faction TEXT PRIMARY KEY, cash INT)");
		
        $this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS alliance (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, requestedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT, world TEXT);");
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
} else {
		$this->prefix = $this->prefs->get("prefix", $this->prefix);
		$this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT, world TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT, world VARCHAR);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS balance(faction TEXT PRIMARY KEY, cash INT)");
		
        $this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS alliance (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, requestedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT, world TEXT);");
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
    }
    public function checkUpdate(): void{
if ($this->prefs->get("update-checker", true)) {
      $this->getLogger()->notice("Checking for updates... Please wait.");
      try {
        if (($version = (new PluginDescription(file_get_contents("https://raw.githubusercontent.com/TheFixerDevelopment/FactionsPro/beta/plugin.yml")))->getVersion()) != $this->getDescription()->getVersion()) {
          $this->getLogger()->notice("A new version: $version is now available! Download the new update here: https://poggit.pmmp.io/ci/TheFixerDevelopment/FactionsPro/FactionsPro");
        } else {
          $this->getLogger()->info("FactionsPro is already updated to the latest version!");
        }
      } catch (\Exception $ex) {
        $this->getLogger()->warning("Unable to check for updates");
      }
    }
}
    public function tagCheck() : void{
if($this->prefs->get("tag-type") == "scoretag"){
$this->getLogger()->info("Plugin enabled! Selected 'scoretag' for faction tags!");
return;
}
if($this->prefs->get("tag-type") == "nametag"){
$this->getLogger()->info("Plugin enabled! Selected 'nametag' for Faction tags!");
return;
}
if($this->prefs->get("tag-type") == "displaytag"){
$this->getLogger()->info("Plugin enabled. Selected ‘displaytag’ for Faction tags!");
return;
} else {
$this->getLogger()->error("Invalid tag type. Select either ‘nametag’, ‘scoretag’ or ‘displaytag’ in config option. Plugin disabled.");
Server::getInstance()->getPluginManager()->disablePlugin($this);
return;
}
}
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) :bool {
        return $this->fCommand->onCommand($sender, $command, $label, $args);
    }
    public function setEnemies($faction1, $faction2) {
        $stmt = $this->db->prepare("INSERT INTO enemies (faction1, faction2) VALUES (:faction1, :faction2);");
        $stmt->bindValue(":faction1", $faction1);
        $stmt->bindValue(":faction2", $faction2);
        $stmt->execute();
    }
	public function unsetEnemies($faction1, $faction2) {
		$stmt = $this->db->prepare("DELETE FROM enemies WHERE (faction1 = :faction1 AND faction2 = :faction2) OR (faction1 = :faction2 AND faction2 = :faction1);");
		$stmt->bindValue(":faction1", $faction1);
		$stmt->bindValue(":faction2", $faction2);
		$stmt->execute();
	}
    public function areEnemies($faction1, $faction2) {
        $result = $this->db->query("SELECT ID FROM enemies WHERE (faction1 = '$faction1' AND faction2 = '$faction2') OR (faction1 = '$faction2' AND faction2 = '$faction1');");
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
        $result = $this->db->query("SELECT ID FROM allies WHERE (faction1 = '$faction1' AND faction2 = '$faction2') OR (faction1 = '$faction2' AND faction2 = '$faction1');");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if (empty($resultArr) == false) {
            return true;
        }
    }
    public function updateAllies($faction) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO alliescountlimit(faction, count) VALUES (:faction, :count);");
        $stmt->bindValue(":faction", $faction);
        $result = $this->db->query("SELECT ID FROM allies WHERE faction1='$faction' OR faction2='$faction';");
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
        return (int) $this->prefs->get("AllyLimitPerFaction");
    }
    public function deleteAllies($faction1, $faction2) {
        $stmt = $this->db->prepare("DELETE FROM allies WHERE (faction1 = '$faction1' AND faction2 = '$faction2') OR (faction1 = '$faction2' AND faction2 = '$faction1');");
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
            if ($this->getServer()->getPlayerExact($row[$i]['player']) instanceof Player) {
                 $team .= TextFormat::ITALIC . TextFormat::AQUA . $row[$i]['player'] . TextFormat::GREEN . " §a§lONLINE" . TextFormat::RESET;
            } else {
                $team .= TextFormat::ITALIC . TextFormat::AQUA . $row[$i]['player'] . TextFormat::RED . " §c§lOFFLINE" . TextFormat::RESET;
            }
            $i = $i + 1;
        }
        $s->sendMessage($this->formatMessage(TextFormat::RED . $rankname . " §5of " . TextFormat::RED . $faction . ":", true));
        $s->sendMessage($team);
    }
    public function getAllAllies($s, $faction) {
        $team = "";
        $result = $this->db->query("SELECT faction1, faction2 FROM allies WHERE faction1='$faction' OR faction2='$faction';");
        $i = 0;
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $alliedFaction = $resultArr['faction1'] != $faction ? $resultArr['faction1'] : $resultArr['faction2'];
            $team .= TextFormat::ITALIC . TextFormat::RED . $alliedFaction . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
            $i = $i + 1;
        }
		if($i > 0) {
			$s->sendMessage($this->formatMessage("§-_(§3_§7)_§cFaction's Allies§7_(§3_§7)_", true));
			$s->sendMessage($team);
		} else {
			$s->sendMessage($this->formatMessage("§2$faction §chas no allies", true));
		}
	}
    public function sendListOfTop10FactionsTo($s) {
        $result = $this->db->query("SELECT faction FROM strength ORDER BY power DESC LIMIT 10;");
        $i = 0;
        $s->sendMessage($this->formatMessage("§7_(§3_§7)_§cTop 10 Factions§7_(§3_§7)_", true));
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $j = $i + 1;
            $cf = $resultArr['faction'];
            $pf = $this->getFactionPower($cf);
            $df = $this->getNumberOfPlayers($cf);
           $s->sendMessage(TextFormat::ITALIC . TextFormat::GOLD . "§6$j -> " . TextFormat::GREEN . "§r§d$cf" . TextFormat::GOLD . " §b| " . TextFormat::RED . "§e$pf STR" . TextFormat::GOLD . " §b| " . TextFormat::LIGHT_PURPLE . "§a$df/" . $this->prefs->get("MaxPlayersPerFaction") . TextFormat::RESET);
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
               $lowercasefaction = strtolower($faction);
		$result = $this->db->query("SELECT faction FROM master WHERE lower(faction)='$lowercasefaction';");
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
        return $this->getNumberOfPlayers($faction) >= $this->prefs->get("MaxPlayersPerFaction");
    }
    public function isNameBanned($name) {
        $bannedNames = file_get_contents($this->getDataFolder() . "BannedNames.txt");
        $isBanned = false;
        if (isset($name) && $this->antispam && $this->antispam->getProfanityFilter()->hasProfanity($name)) $isBanned = true;
        return (strpos(strtolower($bannedNames), strtolower($name)) > 0 || $isBanned);
    }
    public function newPlot($faction, $x1, $z1, $x2, $z2, $level) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO plots (faction, x1, z1, x2, z2, world) VALUES (:faction, :x1, :z1, :x2, :z2, :world);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":x1", $x1);
        $stmt->bindValue(":z1", $z1);
        $stmt->bindValue(":x2", $x2);
        $stmt->bindValue(":z2", $z2);
        $stmt->bindValue(":world", $level);
        $stmt->execute();
    }
    public function drawPlot($sender, $faction, $x, $y, $z, $level, $size) {
        $arm = ($size - 1) / 2;
        $block = new Snow();
        if ($this->cornerIsInPlot($x + $arm, $z + $arm, $x - $arm, $z - $arm, $level->getName())) {
            $claimedBy = $this->factionFromPoint($x, $z, $level->getName());
            $power_claimedBy = $this->getFactionPower($claimedBy);
            $power_sender = $this->getFactionPower($faction);
            if ($this->prefs->get("EnableOverClaim")) {
                if ($power_sender < $power_claimedBy) {
                    $sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy with $power_claimedBy STR. Your faction has $power_sender power. You don't have enough power to overclaim this plot."));
                } else {
                    $sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy with $power_claimedBy STR. Your faction has $power_sender power. Type /f overclaim to overclaim this plot if you want."));
                }
                return false;
            } else {
                $sender->sendMessage($this->formatMessage("Overclaiming is disabled."));
                return false;
            }
        }
        $level->setBlock(new Vector3($x + $arm, $y, $z + $arm), $block);
        $level->setBlock(new Vector3($x - $arm, $y, $z - $arm), $block);
        $this->newPlot($faction, $x + $arm, $z + $arm, $x - $arm, $z - $arm, $level->getName());
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
        $level = $player->getLevel()->getName();
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
            return TextFormat::GREEN . "$string";
        } else {
            return TextFormat::YELLOW . "$string";
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
	public function sendListOfTop10RichestFactionsTo($s){
        $result = $this->db->query("SELECT * FROM balance ORDER BY cash DESC LIMIT 10;");
        $i = 0;
        $s->sendMessage(TextFormat::BOLD.TextFormat::AQUA."§7_(§3_§7)_§cTop 10 Most Wealthy Factions§7_(§3_§7)_".TextFormat::RESET);
        while($resultArr = $result->fetchArray(SQLITE3_ASSOC)){
        	var_dump($resultArr);
            $j = $i + 1;
            $cf = $resultArr['faction'];
            $pf = $resultArr["cash"];
            $s->sendMessage(TextFormat::BOLD.TextFormat::GOLD.$j.". ".TextFormat::RESET.TextFormat::AQUA.$cf.TextFormat::RED.TextFormat::BOLD." §c- ".TextFormat::LIGHT_PURPLE."§d$".$pf);
            $i = $i + 1;
        } 
    }
	public function getSpawnerPrice(string $type) : int {
		$sp = $this->prefs->get("spawnerPrices");
		if(isset($sp[$type])) return $sp[$type];
		return 0;
	}
	public function getEconomy(): EconomyAPI{	
 		$pl = Server::getInstance()->getPluginManager()->getPlugin("EconomyAPI");	
 		if(!$pl) return $pl;	
 		if(!$pl->isEnabled()) return null;	
 		return $pl;	
 	}
    public function onDisable(): void {
        if (isset($this->db)) $this->db->close();
    }
}
