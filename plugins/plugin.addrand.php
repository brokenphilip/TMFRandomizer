<?php

Aseco::addChatCommand('addrand', 'Adds random track from TMX');

Aseco::registerEvent('onNewChallenge2', 'addrand_OnNewChallenge');
Aseco::registerEvent('onStatusChangeTo4', 'addrand_OnPlay');
/*
class Exchange {
	private $game;
	private $url;
	
	public $weight;

	public function __construct($game_, $url_) {
		$this->$game = $game_;
		$this->$url = $url_;
		$this->$weight = 0;
	}
	
	public function getGame() {
		return $this->$game;
	}
	
	public function getURL() {
		return $this->$url;
	}
}
*/

$new_challenge = false;
$addrand_auto = false;

function addrand_OnNewChallenge($aseco) {
	global $addrand_auto, $new_challenge;
	if ($addrand_auto) {
		$new_challenge = true;
	}
}

function addrand_OnPlay($aseco) {
	global $addrand_auto, $new_challenge;
	
	// TODO: /remove last track
	
	if ($addrand_auto && $new_challenge) {
		$new_challenge = false;
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#admin}Automatically rolling random track...'));
		add_rand($aseco);
	}
}

function chat_addrand($aseco, $command) {
	$client = $command['author'];
	
	if (!$aseco->isMasterAdminL($client->login)) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}Only the MasterAdmin can run this command!'), $client->login);
		return;
	}
	
	if ($command['params'] == 'auto') {
		global $addrand_auto;
		if ($addrand_auto) {
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#admin}Random track auto mode: {#highlite}OFF'));
			$addrand_auto = false;
		}
		else {
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#admin}Random track auto mode: {#highlite}ON'));
			$addrand_auto = true;
		}
		return;
	}
	
	add_rand($aseco);
}

function add_track($aseco, $tmx_id, $url)
{
	global $rasp, $jukebox, $jukebox_adminadd;  // from plugin.rasp.php, rasp.settings.php
	$tmxdir = 'Challenges\\Randomizer';
	
	$directory = $aseco->server->trackdir . $tmxdir;
	if (!file_exists($directory)) {
		if (!mkdir($directory)) {
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Failed to create directory: {#highlite}' . $directory));
			return false;
		}
	}
	
	$full_url = ($url).'get.aspx?action=trackgbx&id=';

	if (!is_numeric($tmx_id)) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}TMX ID is not a number!'));
		return false;
	}
	
	if ($tmx_id < 0) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}TMX ID is below 0!'));
		return false;
	}
	
	$tmx_id_trim = ltrim($tmx_id, '0');
	$file = http_get_file($full_url . $tmx_id_trim);
	if ($file === false || file == -1) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Error downloading, or wrong TMX section, or TMX is down!'));
		return false;
	}
	
	$file_size = strlen($file);
	if ($file_size >= 256 * 1024) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}File size is too large: {#highlite}'. round($file_size/1024, 2) . 'KB'));
		return false;
	}

	$separator = substr($aseco->server->trackdir, -1, 1);
	$partial_directory = $tmxdir . $separator . $tmx_id_trim . '.Challenge.gbx';
	$local_file_name = $aseco->server->trackdir . $partial_directory;
	if ($nocasepath = file_exists_nocase($local_file_name)) {
		if (!unlink($nocasepath)) {
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Failed to erase old file: {#highlite}' . $local_file_name));
			return false;
		}
	}
	if (!$local_file = @fopen($local_file_name, 'wb')) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Failed to create file: {#highlite}' . $local_file_name));
		return false;
	}
	if (!fwrite($local_file, $file)) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Failed to save/write file: {#highlite}' . $local_file_name));
		return false;
	}
	fclose($local_file);
	
	$new_track = getChallengeData($local_file_name, false);  // 2nd parm is whether or not to get players & votes required
	if ($new_track['votes'] == 500 && $new_track['name'] == 'Not a GBX file') {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Track does not exist!'));
		unlink($local_file_name);
		return false;
	}
	
	// dummy player to easily obtain entire track list
	$list = new Player();
	getAllChallenges($list, '*', '*');
	// check for track presence on server
	foreach ($list->tracklist as $key) {
		if ($key['uid'] == $new_track['uid']) {
			unlink($local_file_name);
			unset($list);
			
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Track already in server list!'));
			return false;
		}
	}
	
	
	unset($list);
	// rename ID filename to track's name
	$md5new = md5_file($local_file_name);
	$filename = trim(utf8_decode(stripColors($new_track['name'])));
	$filename = preg_replace('/[^A-Za-z0-9 \'#=+~_,.-]/', '_', $filename);
	$filename = preg_replace('/ +/', ' ', preg_replace('/_+/', '_', $filename));
	$partial_directory = $tmxdir . $separator . $filename . '_' . $tmx_id_trim . '.Challenge.gbx';
	// insure unique filename by incrementing sequence number,
	// if not a duplicate track
	$i = 1;
	$dupl = false;
	while ($nocasepath = file_exists_nocase($aseco->server->trackdir . $partial_directory)) {
		$md5old = md5_file($nocasepath);
		if ($md5old == $md5new) {
			$dupl = true;
			$partial_directory = str_replace($aseco->server->trackdir, '', $nocasepath);
			break;
		} else {
			$partial_directory = $tmxdir . $separator . $filename . '_' . $tmx_id_trim . '-' . $i++ . '.Challenge.gbx';
		}
	}
	if ($dupl) {
		unlink($local_file_name);
	} else {
		rename($local_file_name, $aseco->server->trackdir . $partial_directory);
	}

	if ($aseco->server->getGame() == 'TMF' && !$aseco->client->query('CheckChallengeForCurrentServerParams', $partial_directory)) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}CheckChallenge failed: {#highlite}'.$aseco->client->getErrorMessage()));
		return false;
	}
	
	if (!$aseco->client->query('AddChallenge', $partial_directory)) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}AddChallenge failed: {#highlite}'.$aseco->client->getErrorMessage()));
		return false;
	}
	
	$aseco->client->resetError();
	$aseco->client->query('GetChallengeInfo', $partial_directory);
	$track = $aseco->client->getResponse();
	if ($aseco->client->isError()) {
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}GetChallengeInfo failed: {#highlite}'.$aseco->client->getErrorMessage()));
		return false;
	}

	$track['Name'] = stripNewlines($track['Name']);
	// check whether to jukebox as well
	// overrules /add-ed but not yet played track
	if ($jukebox_adminadd) {
		$uid = $track['UId'];
		$jukebox[$uid]['FileName'] = $track['FileName'];
		$jukebox[$uid]['Name'] = $track['Name'];
		$jukebox[$uid]['Env'] = $track['Environnement'];
		$jukebox[$uid]['Login'] = $aseco->server->serverlogin;
		$jukebox[$uid]['Nick'] = $aseco->server->nickname;
		//$jukebox[$uid]['source'] = $source;
		$jukebox[$uid]['tmx'] = false;
		$jukebox[$uid]['uid'] = $uid;
	}

	$track_name = stripColors($track['Name'], false);
	
	// log console message
	$aseco->console('Adding track "{1}" ({2}) from {3}!', $track_name, $tmx_id_trim, $url);

	// throw 'tracklist changed' event
	$aseco->releaseEvent('onTracklistChanged', array('add', $partialdir));

	// throw 'jukebox changed' event
	if ($jukebox_adminadd)
		$aseco->releaseEvent('onJukeboxChanged', array('add', $jukebox[$uid]));
	
	$message = formatText('{#server}>> {#admin}Added: {#highlite}{1} {#admin}by {#highlite}{2} {#admin}on {#highlite}{3}{#admin}!', $track_name, stripColors($track['Author'], false), $track['Environnement']);
	$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
	return true;
}

function add_rand($aseco) {
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
	
	$message = formatText('{#server}>> {#admin}Chosen random exchange: {#highlite}{1}', $game);
	$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
	
	while (true) {
		$headers = get_headers($url .'trackrandom', 1);
		if ($headers !== false && isset($headers['Location'])) {
			$track = str_replace('/trackshow/', '', $headers['Location']);
			
			$message = formatText('{#server}>> {#admin}Found random track: {#highlite}{1}', $track);
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
			
			$content = file_get_contents($url .'api/tracks?fields=PrimaryType,Environment,Car,AuthorTime,TrackName,AuthorComments&id='. ($track));
			$json = json_decode($content);
			
			if ($json->Results[0]->PrimaryType == 1 || $json->Results[0]->PrimaryType == 3) {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#admin}Track is puzzle or stunts, skipping...'));
				continue;
			}
			
			if ($nations && ($json->Results[0]->Environment != 7 || $json->Results[0]->Car != 7)) {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#admin}Track is not nations, skipping...'));
				continue;
			}
			
			if ($json->Results[0]->AuthorTime > 120000) {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#admin}Track is over 2 mins long, skipping...'));
				continue;
			}
			
			// weak match for unlimite(r/d), infinit(e/y)
			// NOTE: some tracks use terms like 1.2 and 1.3 to refer to the unlimiter version, but we're doing more harm than good by filtering them out
			$name = strtolower($json->Results[0]->TrackName);
			$desc = strtolower($json->Results[0]->AuthorComments);
			if (strpos($name, 'unlimite') !== false || strpos($desc, 'unlimite') !== false ||
				strpos($name, 'infinit') !== false || strpos($desc, 'infinit') !== false) {
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#admin}Track is likely TMUnlimiter/Infinity, skipping...'));
				continue;
			}
			
			if (add_track($aseco, $track, $url)) {
				break;
			}
			
			/*
			$cmd['params'] = ($track) .' '. ($game);
			//$aseco->client->query('GetDetailedPlayerInfo', $aseco->server->serverlogin);
			$cmd['author'] = $client;//$aseco->client->getResponse();;
			
			global $feature_tmxadd;
			$feature_tmxadd_old = $feature_tmxadd;
			$feature_tmxadd = true;
			chat_add($aseco, $cmd);
			$feature_tmxadd = $feature_tmxadd_old;
			*/
		}
		else {
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Failed to get header!'));
		}
		
		// only kicks in if failed to get header, or add_track returned false for whatever reason
		$failed_attempts++;
		if ($failed_attempts >= 5) {
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}>> {#error}Too many failed attempts, bailing!'));
			break;
		}
	}
}

?>