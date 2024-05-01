<?php

Aseco::addChatCommand('addrand', 'Adds random track from TMX');

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
	
	$game = "TMU";
	$url = "https://tmuf.exchange/trackrandom";
	
	$rand = rand(1, 100);
	if ($rand <= 2) {
		$game = "TMN";
		$url = "https://nations.tm-exchange.com/trackrandom";
	}
	else if ($rand <= 13) {
		$game = "TMNF";
		$url = "https://tmnf.exchange/trackrandom";
	}
	else if ($rand <= 24) {
		$game = "TMO";
		$url = "https://original.tm-exchange.com/trackrandom";
	}
	else if ($rand <= 35) {
		$game = "TMS";
		$url = "https://sunrise.tm-exchange.com/trackrandom";
	}
	
	$message = formatText('{#server}> {#admin}Chosen random exchange: {#highlite}{1}', $game);
	$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
	
    $headers = get_headers($url, 1);
    if ($headers !== false && isset($headers['Location'])) {
		$track = str_replace("/trackshow/", "", $headers['Location']);
		
		$message = formatText('{#server}> {#admin}Found random track: {#highlite}{1}', $track); 
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
		
		$cmd['params'] = 'add '. ($track) .' '. ($game);
		$cmd['author'] = $command['author'];
		chat_admin($aseco, $cmd);
		return;
    }
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}Failed to get HTML header!'), $client->login);
}

?>