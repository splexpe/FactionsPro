<?php

namespace FactionsPro;

use pocketmine\command\{Command, CommandSender};
use pocketmine\{Server, Player};
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\level\{Level, Position};

class FactionCommands {
	
    public $plugin;
    
    // ASCII Map
	CONST MAP_WIDTH = 50;
	CONST MAP_HEIGHT = 11;
	CONST MAP_HEIGHT_FULL = 17;
	CONST MAP_KEY_CHARS = "\\/#?ç¬£$%=&^ABCDEFGHJKLMNOPQRSTUVWXYZÄÖÜÆØÅ1234567890abcdeghjmnopqrsuvwxyÿzäöüæøåâêîûô";
	CONST MAP_KEY_WILDERNESS = TextFormat::GRAY . "-"; /*Del*/
	CONST MAP_KEY_SEPARATOR = TextFormat::AQUA . "*"; /*Del*/
	CONST MAP_KEY_OVERFLOW = TextFormat::WHITE . "-" . TextFormat::WHITE; # ::MAGIC?
	CONST MAP_OVERFLOW_MESSAGE = self::MAP_KEY_OVERFLOW . ": Too Many Factions (>" . 107 . ") on this Map.";
        
    public function __construct(FactionMain $pg) {
        $this->plugin = $pg;
    }
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if ($sender instanceof Player) {
            $playerName = $sender->getPlayer()->getName();
            if (strtolower($command->getName()) === "f") {
                if (empty($args)) {
                    $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("FactionHelpUsage")));
                    return true;
                }
                    ///////////////////////////////// WAR /////////////////////////////////
                    if(strtolower($args[0]) == "war" or strtolower($args[0]) == "wr"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("WarUsage")));
                            return true;
                        }
                        if (strtolower($args[1]) == "tp" or strtolower($args[1]) == "teleport") {
                            foreach ($this->plugin->wars as $r => $f) {
                                $fac = $this->plugin->getPlayerFaction($playerName);
                                if ($r == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$f][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
                                    return true;
                                }
                                if ($f == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$r][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                                    return true;
                                }
                            }
                            $sender->sendMessage($this->plugin->prefs->get("WarError"));
                            return true;
                        }
                        if (!($this->alphanum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("WarNameError")));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("WarDoesNotExist")));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFacMessage")));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("WarMustBeLeader")));
                            return true;
                        }
                        if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("WarNotEnemy")));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $sFaction = $this->plugin->getPlayerFaction($playerName);
                            foreach ($this->plugin->war_req as $r => $f) {
                                if ($r == $args[1] && $f == $sFaction) {
                                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                        $task = new FactionWar($this->plugin, $r);
                                        $handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                        $task->setHandler($handler);
                                        $p->sendMessage($this->plugin->prefs->get("WarSuccessMessage"));
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                            $this->plugin->war_players[$sFaction][] = $p->getName();
                                        }
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                            $this->plugin->war_players[$factionName][] = $p->getName();
                                        }
                                    }
                                    $this->plugin->wars[$factionName] = $sFaction;
                                    unset($this->plugin->war_req[strtolower($args[1])]);
                                    return true;
                                }
                            }
                            $this->plugin->war_req[$sFaction] = $factionName;
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                        $p->sendMessage($this->plugin->prefs->get("RequestToWar"));
                                        $sender->sendMessage($this->plugin->prefs->get("WarRequestSent"));
                                        return true;
                                    }
                                }
                            }
                            $sender->sendMessage($this->plugin->prefs->get("WarLeaderOffline"));
                            return true;
                        }
                    }
                    /////////////////////////////// CREATE ///////////////////////////////
                    if(strtolower($args[0]) == "create" or strtolower($args[0]) == "make"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("CreateUsage")));
			    $sender->sendMessage($this->plugin->formatMessage("§b§aDescription: §dCreates a faction."));
                            return true;
                        }
                        if (!($this->alphanum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("CreateError")));
                            return true;
                        }
                        if ($this->plugin->isNameBanned($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("CreateNameNotAllowed")));
                            return true;
                        }
                        if ($this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("CreateFacAlreadyExists")));
                            return true;
                        }
                        if (strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MaxFacNameLimit")));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("LeaveBeforeCommand")));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $rank = "Leader";
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", $playerName);
                            $stmt->bindValue(":faction", $factionName);
                            $stmt->bindValue(":rank", $rank);
                            $result = $stmt->execute();
                            $this->plugin->updateAllies($factionName);
                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
			    $this->plugin->setBalance($factionName, $this->plugin->prefs->get("defaultFactionBalance"));
                            if($this->plugin->prefs->get("BroadcastFactionCreationMessage")){
		                $sender->getServer()->broadcastMessage(str_replace([
			            "%PLAYER%",
		                    "%FACTION%"
				    ], [
				    $sender->getName(),
				    $factionName
			        ], $this->plugin->prefs->get("FactionCreationBroadcastMessage")));
			   }
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("CreateSuccessMessage")));
			    var_dump($this->plugin->db->query("SELECT * FROM balance;")->fetchArray(SQLITE3_ASSOC));
                            return true;
                        }
                    }
                    /////////////////////////////// INVITE ///////////////////////////////
                    if(strtolower($args[0]) == "invite" or strtolower($args[0]) == "inv"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("InviteUsage")));
                            return true;
                        }
                        if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($playerName))) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("FacFullMessage")));
                            return true;
                        }
                        $invited = $this->plugin->getServer()->getPlayer($args[1]);
                        if (!($invited instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("InviteNotOnline")));
                            return true;
                        }
                        if ($this->plugin->isInFaction($invited->getName()) == true) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("AlreadyInFac")));
                            return true;
                        }
                        if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                            if (!($this->plugin->isOfficer($playerName) || $this->plugin->isLeader($playerName))) {
                                $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("LeaderNotOnlineMessage")));
                                return true;
                            }
                        }
                        if ($invited->getName() == $playerName) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("InviteSelfMessage")));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($playerName);
                        $invitedName = $invited->getName();
                        $rank = "Member";
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                        $stmt->bindValue(":player", $invitedName);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":invitedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("InvitedNameMessage")));
                        $invited->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("InviteRequestMessage")));
                    }
                    /////////////////////////////// LEADER ///////////////////////////////
                    if ($args[0] == "leader"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("LeaderUsage")));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFacMessage")));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeLeaderMessage")));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustAddPlayer")));
                            return true;
                        }
                        if (!($this->plugin->getServer()->getPlayer($args[1]) instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("LeaderPlayerNotOnline")));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("TransferLeaderShipError")));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($playerName);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $playerName);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Leader");
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NoLongerLeaderMessage")));
                        $this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("TransferShipSuccessMessage")));
                    }
                    /////////////////////////////// PROMOTE ///////////////////////////////
                    if ($args[0] == "promote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PromoteUsage")));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFacMessage")));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeLeaderMessage")));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PlayerNotInFaction")));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PromoteSelfError")));
                            return true;
                        }
                        if ($this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("AlreadyOfficerMessage")));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($playerName);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Officer");
                        $result = $stmt->execute();
                        $promotee = $this->plugin->getServer()->getPlayer($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PromotedToOfficerMessage")));
                        if ($promotee instanceof Player) {
                            $promotee->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PromotedPlayerMessage")));
                            return true;
                        }
                    }
                    /////////////////////////////// DEMOTE ///////////////////////////////
                    if ($args[0] == "demote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("DemoteUsage")));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFacMessage")));
                            return true;
                        }
                        if ($this->plugin->isLeader($playerName) == false) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeLeaderMessage")));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PlayerNotInFaction")));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("DemoteSelfMessage")));
                            return true;
                        }
                        if (!$this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("AlreadyMemberMessage")));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($playerName);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $demotee = $this->plugin->getServer()->getPlayer($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("DemotedSuccessMessage")));
			return true;
                        if ($demotee instanceof Player) {
                            $demotee->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("DemotedPlayerMessage")));
                            return true;
                        }
                    }
                    /////////////////////////////// KICK ///////////////////////////////
                    if(strtolower($args[0]) == "kick" or strtolower($args[0]) == "k"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("KickUsage")));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFacMessage")));
                            return true;
                        }
                        if ($this->plugin->isLeader($playerName) == false) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeLeaderMessage")));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PlayerNotInFaction")));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("KickSelfMessage")));
                            return true;
                        }
                        $kicked = $this->plugin->getServer()->getPlayer($args[1]);
                        $factionName = $this->plugin->getPlayerFaction($playerName);
                        $this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("§aYou successfully kicked §2$args[1]", true));
                        $this->plugin->subtractFactionPower($factionName, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
			$this->plugin->takeFromBalance($factionName, $this->plugin->prefs->get("MoneyGainedPerPlayerInFaction"));
                        if ($kicked instanceof Player) {
                            $kicked->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("KickedSuccessMessage")));
                            return true;
                        }
                    }
                    /////////////////////////////// CLAIM ///////////////////////////////
                    if(strtolower($args[0]) == "claim" or strtolower($args[0]) == "cl"){
				if($this->plugin->prefs->get("ClaimingEnabled") == false){
					$sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("ClaimError")));
					return true;
			}
			if(!$this->plugin->isInFaction($playerName)){
			   $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFacMessage")));
			   return true;
			}
                        if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClaimWorlds"))) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("ClaimOnlyWorlds") . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                            return true;
                        }
                        if ($this->plugin->inOwnPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("AlreadyClaimedMessage")));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {
                            $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NeededToClaimAPlot")));
                            return true;
                        }
                        if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NotEnoughSTRToClaim")));
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NotEnoughSTRToClaim2nd")));
                            return true;
			}
                        if ($this->plugin->getBalance($faction) < $this->plugin->prefs->get("MoneyNeededToClaimAPlot")) {
                            $needed_money = $this->plugin->prefs->get("MoneyNeededToClaimAPlot");
                            $balance = $this->plugin->getBalance($faction);
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NotEnoughMoneyToClaim")));
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NotEnoughMoneyToClaim2nd")));
                            return true;
                        }
                        $x = floor($sender->getX());
			$y = floor($sender->getY());
			$z = floor($sender->getZ());
			$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
			if(!$this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize"))){
				return true;
                        }
			$plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
                        $balance = $this->plugin->getBalance($faction);
			$this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerNeededToClaimAPlot"));
                        $this->plugin->takeFromBalance($faction, $this->plugin->prefs->get("MoneyNeededToClaimAPlot"));
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("ClaimedMessage")));
			return true;
		    }
                    if(strtolower($args[0]) == "plotinfo" or strtolower($args[0]) == "pinfo"){
                        $x = floor($sender->getX());
			$y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if (!$this->plugin->isInPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PlotNotClaimedMessage")));
			    return true;
			}
                        $fac = $this->plugin->factionFromPoint($x, $z);
                        $power = $this->plugin->getFactionPower($fac);
                        $balance = $this->plugin->getBalance($fac);
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PlotClaimedInfoMessage")));
			return true;
                    }
                    if(strtolower($args[0]) == "forcedelete" or strtolower($args[0]) == "fdisband"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("ForceDeleteUsage")));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("FactionNoExistMessage")));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeOPMessage")));
                            return true;
                        }
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
		        $this->plugin->db->query("DELETE FROM balance WHERE faction='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("ForceDeleteSuccessMessage")));
			return true;
                    }
                    if (strtolower($args[0]) == 'addstrto') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("AddSTRTOUsage")));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("FactionNoExist")));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeOPMessage")));
                            return true;
                        }
                        $this->plugin->addFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("AddSTRTOSuccessMessage")));
			return true;
                    }
                    if (strtolower($args[0]) == 'addbalto') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("AddBalToUsage")));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("FactionNoExist")));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeOPMessage")));
                            return true;
                        }
                        $this->plugin->addToBalance($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("AddBalToSuccessMessage")));
			return true;
                    }
		    if (strtolower($args[0]) == 'rmbalto') {
	                if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("RMBaltoUsage")));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("FactionNoExist")));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeOPMessage")));
                            return true;
                        }
                        $this->plugin->takeFromBalance($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("RemovedBalanceSuccess")));
		    }
                    if(strtolower($args[0]) == 'rmpower') {
		       if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("RMPowerUsage")));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("FactionNoExist")));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeOPMessage")));
                            return true;
                        }
                        $this->plugin->subtractFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("RMPowerSuccessMessage")));
			return true;
                    }
                    if(strtolower($args[0]) == "playerfaction" or strtolower($args[0]) == "pf"){
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PFUsage")));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PlayerNotInFaction")));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($args[1]);
			$playerName = $this->plugin->getServer()->getPlayer($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PFInfoMessage")));
                    }
                    
                    if (strtolower($args[0]) == "overclaim" or strtolower($args[0]) == "oc"){
                        if (!$this->plugin->isInFaction($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFaction")));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeLeaderMessage")));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($playerName);
                        if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {
                            $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NotEnoughToOCMessage")));
                            return true;
                        }
                        if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NotEnoughSTRToClaim")));
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NotEnoughSTRToClaim2nd")));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("GetCoordsMessage")));
                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if ($this->plugin->prefs->get("EnableOverClaim")) {
                            if ($this->plugin->isInPlot($sender)) {
                                $faction_victim = $this->plugin->factionFromPoint($x, $z);
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($playerName);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if ($this->plugin->inOwnPlot($sender)) {
                                    $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("OwnPlotMessage")));
                                    return true;
                                } else {
                                    if ($faction_ours_power < $faction_victim_power) {
                                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("CantOCMessage")));
                                        return true;
                                    } else {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
                                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("OCSuccessMessage")));
                                        return true;
                                    }
                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInPlot")));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("OCDisabledMessage")));
                            return true;
                        }
                    }
                    /////////////////////////////// UNCLAIM ///////////////////////////////
                    if(strtolower($args[0]) == "unclaim" or strtolower($args[0]) == "uncl"){
				  if($this->plugin->prefs->get("ClaimingEnabled") == false){
					$sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("ClaimError")));
					return true;
                        }
			if(!$this->plugin->isInFaction($playerName)){
			   $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFaction")));
			   return true;
			}
                        if (!$this->plugin->isLeader($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeLeaderMessage")));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("UnclaimedMessage")));
			return true;
                    }
                    /////////////////////////////// DESCRIPTION ///////////////////////////////
                    if(strtolower($args[0]) == "desc" or strtolower($args[0]) == "motd"){
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFaction")));
                            return true;
                        }
                        if ($this->plugin->isLeader($playerName) == false) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeLeaderMessage")));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("DescMessage")));
			return true;
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                        $stmt->bindValue(":player", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
		    }
		    /////////////////////////////// TOP, also by @PrimusLV //////////////////////////
					if(strtolower($args[0]) == "top" or strtolower($args[0]) == "lb"){
					          if(!isset($args[1])){
					          $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("TopUsage")));
                            		          return true;
			      		          }
						    
					          if(isset($args[1]) && $args[1] == "money"){
                              $this->plugin->sendListOfTop10RichestFactionsTo($sender);
					          }else{
					          if(isset($args[1]) && $args[1] == "str"){
                              $this->plugin->sendListOfTop10FactionsTo($sender);
						           //$this->plugin->sendListOfTop10RichestFactionsTo($sender);
			                          }
			                          return true;
		                          }
                                        }
                    /////////////////////////////// ACCEPT ///////////////////////////////
                    if(strtolower($args[0]) == "accept" or strtolower($args[0]) == "yes"){
                        $lowercaseName = strtolower($playerName);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou have not been invited to any factions"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= $this->plugin->prefs->get("InviteTime")) {
                            $faction = $array["faction"];
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", ($playerName));
                            $stmt->bindValue(":faction", $faction);
                            $stmt->bindValue(":rank", "Member");
                            $result = $stmt->execute();
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("JoinedFacMessage")));
			    return true;
                            $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
			    $this->plugin->addToBalance($faction, $this->plugin->prefs->get("MoneyGainedPerPlayerInFaction"));
                            $this->plugin->getServer()->getPlayer($array["invitedby"])->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("JoinMessageBroadcast")));
			    return true;
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("InvTimedOut")));
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$playerName';");
                        }
                    }
                    /////////////////////////////// DENY ///////////////////////////////
                    if(strtolower($args[0]) == "deny" or strtolower($args[0]) == "no"){
                        $lowercaseName = strtolower($playerName);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("NotInvToFaction")));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= $this->plugin->prefs->get("InviteTime")) {
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("InvDenyMessage")));
			    return true;
                            $this->plugin->getServer()->getPlayer($array["invitedby"])->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("PlayerDeniedInvMessage")));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInvite has timed out"));
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                        }
                    }
                    /////////////////////////////// DELETE ///////////////////////////////
                    if(strtolower($args[0]) == "del" or strtolower($args[0]) == "disband"){
                        if ($this->plugin->isInFaction($playerName) == true) {
                            if ($this->plugin->isLeader($playerName)) {
                                $faction = $this->plugin->getPlayerFaction($playerName);
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                                $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
			        $this->plugin->db->query("DELETE FROM balance WHERE faction='$faction';");
                                $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("DeleteMessage")));
				return true;
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeLeaderMessage")));
				return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage($this->plugin->prefs->get("MustBeInFaction")));
			    return true;
                        }
