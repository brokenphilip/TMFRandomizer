<?php

Aseco::addChatCommand('addrand', 'Adds random track from TMX');

class Exchange {
	private $game;
	private $url;
	
	public $weight;

	public function __construct(string $game_, string $url_) {
		$this->$game = $game_;
		$this->$url = $url_;
		$this->$weight = 0;
	}
	
	public function getGame() : string {
		return $this->$game;
	}
	
	public function getURL() : string {
		return $this->$url;
	}
}

function chat_addrand($aseco, $command) {
	$client = $command['author'];
	
	//if (!$aseco->isMasterAdmin($admin)) {
	//	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}Only the MasterAdmin can run this command.'), $client->login);
	//	return;
	//}
	
	// 30-Apr-24: 1047k tracks
	//
	// TMNF has 604k tracks - 58%
	// TMUF has 240k tracks - 23%
    // TMN  has 143k tracks - 14%
	// TMS  has 34k  tracks - 3%
    // TMO  has 26k  tracks - 2%
	// 
	// TMS  / 3 = 11k
	// TMO  / 3 = 9k
	// TMUF / 7 = 34k
	//
	// Desert  "has" 43k  tracks - 4%
	// Rally   "has" 43k  tracks - 4%
	// Snow    "has" 43k  tracks - 4%
	// Bay     "has" 45k  tracks - 4%
	// Coast   "has" 45k  tracks - 4%
	// Island  "has" 45k  tracks - 4%
	// Stadium "has" 783k tracks - 76%
	//
	// DRSBCI - 25% tmo/tms 75% tmuf (3/7 + 3/7)
	// stadiu - 75% tmnf 5% tmuf 20% tmn (1/7)
	
	// tmn  - 2%
	// tmnf - 11%
	// tmo  - 11%
	// tms  - 11%
	// tmuf - 65%
	
	/*
	$exchanges = array();
	
	$desert = false;
	$rally = false;
	$snow = false;
	$bay = false;
	$coast = false;
	$island = false;
	$stadium = false;
	
	if ($aseco->server->packmask == 'Desert') {
		$desert = true;
	}
	else if ($aseco->server->packmask == 'Rally') {
		$rally = true;
	}
	else if ($aseco->server->packmask == 'Snow') {
		$snow = true;
	}
	else if ($aseco->server->packmask == 'Bay') {
		$bay = true;
	}
	else if ($aseco->server->packmask == 'Coast') {
		$coast = true;
	}
	else if ($aseco->server->packmask == 'Island') {
		$island = true;
	}
	else if ($aseco->server->packmask == 'Stadium') {
		$stadium = true;
	}
	else if ($aseco->server->packmask == 'Original') {
		$desert = true;
		$rally = true;
		$snow = true;
	}
	else if ($aseco->server->packmask == 'Sunrise') {
		$bay = true;
		$coast = true;
		$island = true;
	}
	else if ($aseco->server->packmask == 'United') {
		$desert = true;
		$rally = true;
		$snow = true;
		$bay = true;
		$coast = true;
		$island = true;
		$stadium = true;
	}
	
	if ($stadium) {
		array_push($exchanges, new Exchange("TMNF", "https://tmnf.exchange/", 75));
		array_push($exchanges, new Exchange("TMUF", "https://tmuf.exchange/", 5));
		array_push($exchanges, new Exchange("TMN", "https://nations.tm-exchange.com/", 20));
	}
	*/
	
	$game = "TMU";
	$url = "https://tmuf.exchange/";
	
	$nations = $aseco->server->packmask == 'Stadium';
	
	if ($nations) {
		$rand = rand(1, 100);
		if ($rand <= 75) {
			$game = "TMNF";
			$url = "https://tmnf.exchange/";
		}
		else if ($rand <= 95) {
			$game = "TMN";
			$url = "https://nations.tm-exchange.com/";
		}
	}
	else {
		$rand = rand(1, 100);
		if ($rand <= 2) {
			$game = "TMN";
			$url = "https://nations.tm-exchange.com/";
		}
		else if ($rand <= 13) {
			$game = "TMNF";
			$url = "https://tmnf.exchange/";
		}
		else if ($rand <= 24) {
			$game = "TMO";
			$url = "https://original.tm-exchange.com/";
		}
		else if ($rand <= 35) {
			$game = "TMS";
			$url = "https://sunrise.tm-exchange.com/";
		}
	}
	
	$message = formatText('{#server}> {#admin}Chosen random exchange: {#highlite}{1}', $game);
	$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
	
	while (true) {
		$headers = get_headers($url .'trackrandom', 1);
		if ($headers !== false && isset($headers['Location'])) {
			$track = str_replace('/trackshow/', '', $headers['Location']);
			
			$message = formatText('{#server}> {#admin}Found random track: {#highlite}{1}', $track); 
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
			
			$content = file_get_contents($url .'api/tracks?fields=PrimaryType,Environment,Car,AuthorTime,TrackName,AuthorComments&id='. ($track));
			$json = json_decode($content);
			
			if ($json->Results[0]->PrimaryType == 'Puzzle' || $json->Results[0]->PrimaryType == 'Stunts') {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}> {#admin}Track is puzzle or stunts, skipping...'));
				continue;
			}
			
			if ($nations && ($json->Results[0]->Environment != 7 || $json->Results[0]->Car != 7)) {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}> {#admin}Track is not nations, skipping...'));
				continue;
			}
			
			if ($json->Results[0]->AuthorTime > 120000) {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}> {#admin}Track is over 2 mins long, skipping...'));
				continue;
			}
			
			// weak match for unlimite(r/d), infinit(e/y)
			$name = strtolower($json->Results[0]->TrackName);
			$desc = strtolower($json->Results[0]->AuthorComments);
			if (strpos($name, 'unlimite') !== false || strpos($desc, 'unlimite') !== false || strpos($name, 'infinit') !== false || strpos($desc, 'infinit') !== false) {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}> {#admin}Track is likely TMUnlimiter/Infinity, skipping...'));
				continue;
			}
			
			$cmd['params'] = 'add '. ($track) .' '. ($game);
			$aseco->client->query('GetDetailedPlayerInfo', 'bp_srv');
			$cmd['author'] = $aseco->client->getResponse();
			chat_admin($aseco, $cmd);
			break;
		}
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}> {#error}Failed to get header, bailing...'));
		break;
	}
}

?>