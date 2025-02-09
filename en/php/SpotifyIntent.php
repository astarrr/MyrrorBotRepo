<?php

require_once  'Affects.php'; //Per le emozioni
require_once  'Demographics.php'; //Per l'età
require_once  'Behaviors.php'; //Per i dati sull'attività fisica

include 'connection_spotify.php';
include 'spotifyFetch.php';

/*Funzione principale
	Controllo se è una canzone oppure una playlist (se non sono presenti le parole elencate, sarà presa una playlist)
	Vedo se sono presenti le parole relative alle emozioni ecc
	Controllo che se ci sono presenti parametri any oppure artist oppure generi
	Se non viene trovata nessuna delle precedenti, si andrà a consigliare la canzone
	explanation sarà valorizzata solo per musica personalizzata ed emozioni*/
function getMusic($resp,$parameters,$text,$email){
	
	$flagBrano = false;
	$flagRaccomandazioni = false;
	$flagAny = false;
	$flagArtist = false;
	$flagGenere = false;
	$flagEmozioni = false;

	$spiegazione = "";
	$param = "";
	
	$listaParoleBrano = array( 'track', 'song', 'music' );//brano
	$listaParoleRaccomandazioni = array( 'suitable for me', 'recommend', 'recommend me', 'suggest', 'for me' , 'me');//raccomandazioni
	$listaParoleEmozioni = array( 'mood', 'emotion');//emozioni

	//Controllo se sono presenti le parole delle raccomandazioni allora vado nella sezione delle PLAYLIST RACCOMANDATE
	foreach($listaParoleRaccomandazioni as $parola)  {  
   		if (stripos($text, $parola) !== false) {
    		//Contiene la parola
   			$flagRaccomandazioni = true;
   			break;
		} 
   	}

	//Controllo se sono presenti le parole delle emozioni
   	foreach($listaParoleEmozioni as $parola)  {  
   		if (stripos($text, $parola) !== false) {
    		//Contiene la parola
   			$flagEmozioni = true;
   			break;
		} 
   	}

	//Controllo se sono presenti le parole del singolo brano
   	foreach($listaParoleBrano as $parola)  {  
   		if (stripos($text, $parola) !== false) {
    		//Contiene la parola
   			$flagBrano = true;
   			break;
		} 
	}

	if ($flagBrano == true && $flagEmozioni == true) {
		//echo "canzone in base alle emozioni";
		$answer = getMusicByEmotion($resp,$parameters,$text,$email);
		$spiegazione = explainMusicEmotion($resp,$parameters,$text,$email);

	}else if($flagEmozioni == true){
		//echo "playlist in base alle emozioni";
		$answer = getPlaylistByEmotion($resp,$parameters,$text,$email);
		$spiegazione = explainMusicEmotion($resp,$parameters,$text,$email);

	}else{
		if ($flagRaccomandazioni == true) { //PLAYLIST RACCOMANDATE

	   	 	if ($parameters['GeneriMusicali'] != "") {
	   	 		$flagGenere = true;
	   	 		//echo "playlist in base al genere richiesto";
	   	 		$answer = getMusicByGenre($resp,$parameters,$text,$email);
	   	 	}else if($flagEmozioni == true){
	   	 		//echo "playlist in base alle emozioni";
	   	 		$answer = getPlaylistByEmotion($resp,$parameters,$text,$email);
	   	 		$spiegazione = explainMusicEmotion($resp,$parameters,$text,$email);
	   	 	}else{
	   	 		//echo "playlist raccomandata";
	   	 		$answer = getMusicCustom($resp,$parameters,$text,$email);
	   	 		$spiegazione = explainCustomMusic($resp,$parameters,$text,$email);
	   	 	}

   		} else{//Effettuo i controlli e verifico se si tratta di un brano oppure una playlist

	   		//Vedo se è valorizzato il genere
	   		if ($parameters['GeneriMusicali'] != "") {
	   			$flagGenere = true;
	   			//echo "playlist in base al genere richiesto";
	   			$answer = getMusicByGenre($resp,$parameters,$text,$email);
	   			$param = $parameters['GeneriMusicali'];
	   		}else{

	   			foreach($listaParoleBrano as $parola)  {  
			   		if (stripos($text, $parola) !== false) {
			    		//Contiene la parola
			   			$flagBrano = true;
			   			break;
					} 
				}

				//Vedo se è valorizzato any
				if ($parameters['any'] != "") {
					$flagAny = true;
					$flagBrano = true;
				}

				//Vedo se è valorizzato music-artist
				if ($parameters['music-artist'] != "") {
					$flagArtist = true;
				}

				if ($flagBrano == true || $flagAny == true) {//Brano

		   			if ($flagAny == true) {//Prendo il titolo del brano
		   				//echo "brano con il titolo";
		   				$answer = getMusicByTrack($resp,$parameters,$text,$email);
		   				$param = $parameters['any'];

		   			}else{//Verifico se è presente il nome dell'artista

		   				if ($flagArtist == true) {//Prendo il nome dell'artista del brano
		   					//echo "brano con il nome dell'artista";
		   					$answer = getMusicByArtist($resp,$parameters,$text,$email);
		   					$param = $parameters['music-artist'];
		   				}else{
		   					//RACCOMANDO UNA PLAYLIST DI CANZONI
		   					//echo "playlist raccomandata";
		   					$answer = getMusicCustom($resp,$parameters,$text,$email);
		   					$spiegazione = explainCustomMusic($resp,$parameters,$text,$email);
		   				}

		   			}

		   		}else{ //Playlist

		   			if ($flagAny == true) {//Prendo la playlist
		   				//echo "playlist con il titolo"; -- NIENTE--
		   			}else{//Verifico se è presente il nome dell'artista
		   				if ($flagArtist == true) {//Prendo il nome dell'artista della playlist
		   					//echo "playlist con il nome dell'artista";
		   					$answer = getPlaylistByArtist($resp,$parameters,$text,$email);
		   					$param = $parameters['music-artist'];
		   				}else{
		   					//RACCOMANDO UNA PLAYLIST DI CANZONI
		   					//echo "playlist raccomandata";
		   					$answer = getMusicCustom($resp,$parameters,$text,$email);
		   					$spiegazione = explainCustomMusic($resp,$parameters,$text,$email);
		   				}
		   			}
		   		} 	
	   		}
	   	}
	}

	return array("param" => $param, "url" => $answer,"explain" => $spiegazione);
	
}

//Permette di ottenere il brano richiesto dall'utente e mostrarlo a schermo
function getMusicByTrack($resp,$parameters,$text,$email){

	$api = getApi(); //Api per Spotify

	//Verifico se è stata riconosciuta la canzone inserita dall'utente
	if ($parameters['any'] != "") { 

		$brano = $parameters['any']; //Canzone dell'utente

		//Verifico se è presente anche l'artista del brano
		if ($parameters['music-artist'] != "") {
			$artista = $parameters['music-artist']; //Artista del brano
		}else{
			$artista = "";
		}

		$results = $api->search($brano, 'track');

		//Prendo il primo risultato nel formato di Spotify
		foreach ($results->tracks->items as $track) {
    		$trackName = $track->name; //Nome canzone Spotify
    		$url = $track->external_urls->spotify; //Url canzone Spotify
    		break;
		}

		if (isset($url)) {
			/*
			Aggiungo alla url di Spotify la parola embed/ altrimenti l'iframe non verrà visualizzato per problemi di Copyright
			Esempio:
			https://open.spotify.com/track/2J9TGb5CRT4omfAgnKmXn5 ----> https://open.spotify.com/embed/track/2J9TGb5CRT4omfAgnKmXn5
			*/
			$answer = substr_replace($url, "embed/", 25, 0);
		}else{
			$answer = "Sorry but I couldn't understand the song to play. Try rewriting it in other words";
		}
		return $answer;

	}else{
		$brano = "";
		$artista = "";
		return "Sorry but I couldn't understand the song to play. Try rewriting it in other words";
	}

}

//Permette di ottenere un brano casuale in relazione all'artista richiesto
function getMusicByArtist($resp,$parameters,$text,$email){

	$api = getApi(); //Api per Spotify

	//Verifico se è presente l'artista del brano
	if ($parameters['music-artist'] != "") {
		$artista = $parameters['music-artist']; //Artista del brano
	}else{
		$artista = "";
		return "I'm sorry but I couldn't identify the artist of the track. Try to rewrite it!";
	}

	$results = $api->search($artista, 'track');

	$nomiBrani = array(); 

	//Prendo tutti i brani di quell'artista
	foreach ($results->tracks->items as $track) {
		$nomiBrani[] = $track;
	}

	//Numero casuale
	$num = rand(0,count($nomiBrani)-1);

	//Prendo il brano corrispondente
	$brano = $nomiBrani[$num];
	$trackName = $brano->name; //Nome canzone Spotify
	$url = $brano->external_urls->spotify; //Url canzone Spotify
	
	/*
	Aggiungo alla url di Spotify la parola embed/ altrimenti l'iframe non verrà visualizzato per problemi di Copyright
	Esempio:
	https://open.spotify.com/track/2J9TGb5CRT4omfAgnKmXn5 ----> https://open.spotify.com/embed/track/2J9TGb5CRT4omfAgnKmXn5
	*/
	$answer = substr_replace($url, "embed/", 25, 0);
	
	return $answer;

}

//Permette di ottenere una playlist in relazione ad un determinato genere richiesto
function getMusicByGenre($resp,$parameters,$text,$email){

	$api = getApi(); //Api per Spotify

	//Verifico se è presente il genere del brano
	if ($parameters['GeneriMusicali'] != "") {
		$genere = $parameters['GeneriMusicali']; //Genere del brano
	}else{
		$genere = "";
		return "Sorry but I couldn't identify the genre of the song. Try to rewrite it!";
	}

	//Prendo la playlist di quel genere
	$playlists = $api->getCategoryPlaylists(strtolower($genere), [
    	'country' => 'se',
	]);


	$nomiPlaylist = array(); 

	//Prendo tutte le playlist di quel genere
	foreach ($playlists->playlists->items  as $playlist) {
		$nomiPlaylist[] = $playlist;
	}

	//Numero casuale
	$num = rand(0,count($nomiPlaylist)-1);

	//Prendo la playlist corrispondente
	$playlist = $nomiPlaylist[$num];
	$playlistName = $playlist->name; //Nome playlist Spotify
	$url = $playlist->external_urls->spotify; //Url playlist Spotify

	/*
	Aggiungo alla url di Spotify la parola embed/ altrimenti l'iframe non verrà visualizzato per problemi di Copyright
	Esempio:
	https://open.spotify.com/track/2J9TGb5CRT4omfAgnKmXn5 ----> https://open.spotify.com/embed/track/2J9TGb5CRT4omfAgnKmXn5
	*/
	$answer = substr_replace($url, "embed/", 25, 0);
	
	return $answer;

}

//Permette di ottenere una playlist in relazione ad un artista richiesto
function getPlaylistByArtist($resp,$parameters,$text,$email){

	$api = getApi(); //Api per Spotify

	//Verifico se è presente il genere del brano
	if ($parameters['music-artist'] != "") {
		$artista = $parameters['music-artist']; //Genere del brano
	}else{
		$artista = "";
		return "I'm sorry but I couldn't identify the artist of the piece. Try to rewrite it!";
	}

	$results = $api->search($artista, 'track');
	//print_r($results);

	//Cerco il nome dell'artista in $results e prendo il suo id
	foreach ($results->tracks  as $track) {
		if (is_array($track)) {
			foreach ($track as $value) {
				foreach ($value->album as $album) {
					if (is_array($album)) {
						foreach ($album as $value) {
							if (isset($value->name)) {
								if ($value->name == $parameters['music-artist']) {
									$idArtist = $value->id;
								}
							}
						}
					}
				}
			}
		}
		
	}

	if (isset($idArtist)) {
		$brani = $api->getArtistTopTracks($idArtist,[
    	'country' => 'it',
		]);
	}else{
		return "Excuse me but I could not identify the requested artist. Try to rewrite it!";
	}

	$arrayAlbum = array();
	foreach ($brani->tracks as $track) {
		foreach ($track as  $value) {
			if (isset($value->external_urls)) {
				foreach ($value->external_urls as $value2) {
					array_push($arrayAlbum, $value2);
				}
			}
		}
	}

	$i = rand(0,count($arrayAlbum) -1);
	$url = $arrayAlbum[$i];	

	/*
	Aggiungo alla url di Spotify la parola embed/ altrimenti l'iframe non verrà visualizzato per problemi di Copyright
	Esempio:
	https://open.spotify.com/track/2J9TGb5CRT4omfAgnKmXn5 ----> https://open.spotify.com/embed/track/2J9TGb5CRT4omfAgnKmXn5
	*/
	$answer = substr_replace($url, "embed/", 25, 0);

	return $answer;

}

//Permette di ottenere una playlist in relazione alle emozioni che si sta provando. Verranno rilevate le più recenti emozioni
function getPlaylistByEmotion($resp,$parameters,$text,$email){

	if ($email == '') {
		return '';
	}

	$api = getApi(); //Api per Spotify

	$emotion = getLastEmotion($email); //Rilevo l'ultima emozione dell'utente

	switch ($emotion) {
      case 'gioia':
      	switch (rand(1,3)) {
      		case 1:
      			$idPlaylist = "37i9dQZF1DX7KNKjOK0o75"; //Have a great Day
      			break;
      		case 2:
      			$idPlaylist = "2s97g3N5GVkxdcuqZFVMFJ"; //Wake me happy
      			break;
      		case 3:
      			$idPlaylist = "37i9dQZF1DWVu0D7Y8cYcs"; //Just smile
      			break;
      	}
        
        break;
      case 'paura':
        switch (rand(1,3)) {
      		case 1:
      			$idPlaylist = "37i9dQZF1DXdxcBWuJkbcy"; //Motivation Mix
      			break;
      		case 2:
      			$idPlaylist = "37i9dQZF1DX1OY2Lp0bIPp"; //Monday Motivation
      			break;
      		case 3:
      			$idPlaylist = "37i9dQZF1DX3YSRoSdA634"; //Life sucks
      			break;
      	}
        break;
      case 'rabbia':
		  switch (rand(1,3)) {
		  		case 1:
		  			$idPlaylist = "37i9dQZF1DXc0aozDLZsk7"; //No Stress
		  			break;
		  		case 2:
		  			$idPlaylist = "37i9dQZF1DX843Qf4lrFtZ"; //Young, wild & free
		  			break;
		  		case 3:
		  			$idPlaylist = "37i9dQZF1DWUvQoIOFMFUT"; //The stress buster
		  			break;
		  	}
        
        break;
      case 'disgusto':
      		switch (rand(1,2)) {
	      		case 1:
	      			$idPlaylist = "37i9dQZF1DWVrtsSlLKzro"; //Sad beats
	      			break;
	      		case 2:
	      			$idPlaylist = "37i9dQZF1DWX83CujKHHOn"; //Alone again
	      			break;
	      	}
        
        break;
      case 'tristezza':
        	switch (rand(1,3)) {
	      		case 1:
	      			$idPlaylist = "37i9dQZF1DWTpgpHHF8zH5"; //Operazione buonumore!
	      			break;
	      		case 2:
	      			$idPlaylist = "37i9dQZF1DWSf2RDTDayIx"; //Happy beats
	      			break;
	      		case 3:
	      			$idPlaylist = "2PT4XWsSDmHmT0pwbvjG32"; //Happy hits!
	      			break;
	      	}
        break;
      case 'sorpresa':
        	switch (rand(1,3)) {
	      		case 1:
	      			$idPlaylist = "324x8j60JqRzd54P1eFUAx"; //Sorridi!
	      			break;
	      		case 2:
	      			$idPlaylist = "2ubLUYx27aQEbUkUl5MYU2"; //Canto sotto la doccia
	      			break;
	      		case 3:
	      			$idPlaylist = "37i9dQZF1DX7P3Ec4TfanK"; //Il caffè del buongiorno
	      			break;
	      	}
        break;
      default: //Non sta provando alcuna emozione
          	switch (rand(1,3)) {
	      		case 1:
	      			$idPlaylist = "37i9dQZF1DX6wfQutivYYr"; //Hot hits italia
	      			break;
	      		case 2:
	      			$idPlaylist = "359Eef7ftG3MiMK0UjDxfU"; //Power it
	      			break;
	      		case 3:
	      			$idPlaylist = "37i9dQZF1DX6ThddIjWuGT"; //Latin Pop Classic
	      			break;
	      	}
        break;
    }


    //Prendo la playlist relativa all'id
	$playlist = $api->getUserPlaylist('username', $idPlaylist);
	$url = $playlist->external_urls->spotify; //Url playlist 	

	/*
	Aggiungo alla url di Spotify la parola embed/ altrimenti l'iframe non verrà visualizzato per problemi di Copyright
	Esempio:
	https://open.spotify.com/track/2J9TGb5CRT4omfAgnKmXn5 ----> https://open.spotify.com/embed/track/2J9TGb5CRT4omfAgnKmXn5
	*/
	$answer = substr_replace($url, "embed/", 25, 0);

	return $answer;

}

function explainMusicEmotion($resp,$parameters,$text,$email){

	if ($email == '') {
		return '';
	}


$emotion = getLastEmotion($email); 

$answer = "I recommended this song to you ";

 switch ($emotion) {
      case 'gioia':
        $answer .= "you're happy &#x1f601";
        break;

      case 'paura':
       $answer .= "you're scared &#x1f628";
        break;

      case 'rabbia':
        $answer .= "you're angry &#x1f621";
        break;

      case 'disgusto':
        $answer .= "you're disgusted &#x1f629";
        break;

      case 'tristezza':
       $answer .= "you're sad &#x1f625";
        break;

      case 'sorpresa':
        $answer .= "you're suprised &#x1f631";
        break;
      
      default:
       $answer .= "your mood is neutral  &#x1f636";
        break;
    }


return $answer;


}

//Permette di ottenere dei brani in relazione alle emozioni che si sta provando. Verranno rilevate le più recenti emozioni
function getMusicByEmotion($resp,$parameters,$text,$email){

	if ($email == '') {
		return '';
	}


	$api = getApi(); //Api per Spotify

	$emotion = getLastEmotion($email); //Rilevo l'ultima emozione dell'utente

	switch ($emotion) {
      case 'gioia':
      	switch (rand(1,3)) {
      		case 1:
      			$idPlaylist = "37i9dQZF1DX7KNKjOK0o75"; //Have a great Day
      			break;
      		case 2:
      			$idPlaylist = "2s97g3N5GVkxdcuqZFVMFJ"; //Wake me happy
      			break;
      		case 3:
      			$idPlaylist = "37i9dQZF1DWVu0D7Y8cYcs"; //Just smile
      			break;
      	}
        
        break;
      case 'paura':
        switch (rand(1,3)) {
      		case 1:
      			$idPlaylist = "37i9dQZF1DXdxcBWuJkbcy"; //Motivation Mix
      			break;
      		case 2:
      			$idPlaylist = "37i9dQZF1DX1OY2Lp0bIPp"; //Monday Motivation
      			break;
      		case 3:
      			$idPlaylist = "37i9dQZF1DX3YSRoSdA634"; //Life sucks
      			break;
      	}
        break;
      case 'rabbia':
		  switch (rand(1,3)) {
		  		case 1:
		  			$idPlaylist = "37i9dQZF1DXc0aozDLZsk7"; //No Stress
		  			break;
		  		case 2:
		  			$idPlaylist = "37i9dQZF1DX843Qf4lrFtZ"; //Young, wild & free
		  			break;
		  		case 3:
		  			$idPlaylist = "37i9dQZF1DWUvQoIOFMFUT"; //The stress buster
		  			break;
		  	}
        
        break;
      case 'disgusto':
      		switch (rand(1,2)) {
	      		case 1:
	      			$idPlaylist = "37i9dQZF1DWVrtsSlLKzro"; //Sad beats
	      			break;
	      		case 2:
	      			$idPlaylist = "37i9dQZF1DWX83CujKHHOn"; //Alone again
	      			break;
	      	}
        
        break;
      case 'tristezza':
        	switch (rand(1,3)) {
	      		case 1:
	      			$idPlaylist = "37i9dQZF1DWTpgpHHF8zH5"; //Operazione buonumore!
	      			break;
	      		case 2:
	      			$idPlaylist = "37i9dQZF1DWSf2RDTDayIx"; //Happy beats
	      			break;
	      		case 3:
	      			$idPlaylist = "2PT4XWsSDmHmT0pwbvjG32"; //Happy hits!
	      			break;
	      	}
        break;
      case 'sorpresa':
        	switch (rand(1,3)) {
	      		case 1:
	      			$idPlaylist = "324x8j60JqRzd54P1eFUAx"; //Sorridi!
	      			break;
	      		case 2:
	      			$idPlaylist = "2ubLUYx27aQEbUkUl5MYU2"; //Canto sotto la doccia
	      			break;
	      		case 3:
	      			$idPlaylist = "37i9dQZF1DX7P3Ec4TfanK"; //Il caffè del buongiorno
	      			break;
	      	}
        break;
      default: //Non sta provando alcuna emozione
          	switch (rand(1,3)) {
	      		case 1:
	      			$idPlaylist = "37i9dQZF1DX6wfQutivYYr"; //Hot hits italia
	      			break;
	      		case 2:
	      			$idPlaylist = "359Eef7ftG3MiMK0UjDxfU"; //Power it
	      			break;
	      		case 3:
	      			$idPlaylist = "37i9dQZF1DX6ThddIjWuGT"; //Latin Pop Classic
	      			break;
	      	}
        break;
    }


    //Prendo la playlist relativa all'id
	$playlist = $api->getUserPlaylistTracks('username', $idPlaylist);

	$listaBrani = array();

	foreach ($playlist->items  as $pl) {
		foreach ($pl as $key => $value) {
			if (isset($value->external_urls)) {

				foreach ($value->external_urls as $track) {
					if (stripos($track, 'track')) {
						$listaBrani[] = $track;
					}

				}
			}
		}
	}

	$num = rand(0,count($listaBrani)-1);
	$url = $listaBrani[$num]; //Url brano 

	/*
	Aggiungo alla url di Spotify la parola embed/ altrimenti l'iframe non verrà visualizzato per problemi di Copyright
	Esempio:
	https://open.spotify.com/track/2J9TGb5CRT4omfAgnKmXn5 ----> https://open.spotify.com/embed/track/2J9TGb5CRT4omfAgnKmXn5
	*/
	$answer = substr_replace($url, "embed/", 25, 0);

	return $answer;

}


function  explainCustomMusic($resp,$parameters,$text,$email){

	if ($email == '') {
		return '';
	}


$eta = getEta($resp,$parameters,$text,$email); //Prendo la data dell'utente

$valori = getLastAttivitaFisica($resp,$parameters,$text,$email); //Prendo i valori sull'attività fisica
$minutiEffettuati = $valori['abbastanzaAttiva'] + $valori['pocoAttiva'] + $valori['moltoAttiva'];

	$answer = "I recommended this playlist because ";

	if ($eta < 20 ) {
		$answer .= "you are young ";
	}elseif ($eta < 50 ) {
		$answer .= "you're an adult "; 
	}else{
        $answer .= "you are of advanced age ";
	}

	if ($minutiEffettuati >= 30) {
		$answer .= "and you have an active lifestyle";
	}else{
		$answer .= "and sedentary";
	}

	return $answer;


}

//Permette di ottenere dei brani personalizzati
function getMusicCustom($resp,$parameters,$text,$email){

	if ($email == '') {
		return '';
	}


	$api = getApi(); //Api per Spotify

	$eta = getEta($resp,$parameters,$text,$email); //Prendo la data dell'utente

	$valori = getLastAttivitaFisica($resp,$parameters,$text,$email); //Prendo i valori sull'attività fisica

	

	//TEST----------------------------------------------------------
	/*$eta = 65;
	$valori = [
    	'abbastanzaAttiva' => 0,
    	'pocoAttiva' => 0,
    	'moltoAttiva' => 24,
  	];*/
  	//------------------------------------------------------------------

  	$soglia = 30; //minuti
  	$minutiEffettuati = $valori['abbastanzaAttiva'] + $valori['pocoAttiva'] + $valori['moltoAttiva'];

  	if ($eta != 0 && $valori != 0) {

  		if ($eta < 20) {//ADOLESCENTE
			if ($minutiEffettuati < $soglia) {//SEDENTARIO
				switch (rand(1,2)) {
					case 1: //POP
						$genere ="pop";
						break;
					case 2: //RAP
						$genere ="rap";
						break;
				}
				
			}else{//ATTIVO
				switch (rand(1,2)) {
					case 1: //PUNK
						$genere ="punk";
						break;
					case 2: //METAL
						$genere ="metal";
						break;
				}
			}
			
		}elseif ($eta >= 20 && $eta <= 50) {//ADULTO
			if ($minutiEffettuati < $soglia) {//SEDENTARIO
				switch (rand(1,3)) {
					case 1: //RELAX
						$genere ="chill";
						break;
					case 2: //ROMANTICA
						$genere ="romance";
						break;
					case 3: //BLUES
						$genere ="blues";
						break;
				}
			}else{//ATTIVO
				switch (rand(1,3)) {
					case 1: //ELECTRONIC
						$genere ="edm_dance";
						break;
					case 2: //ESTATE
						$genere ="summer";
						break;
					case 3: //ROCK
						$genere ="rock";
						break;
				}
			}
			
		}elseif ($eta > 50) {//MEZZA ETA'
			if ($minutiEffettuati < $soglia) {//SEDENTARIO
				switch (rand(1,2)) {
					case 1: //CLASSICA
						$genere ="classical";
						break;
					case 2: //JAZZ
						$genere ="jazz";
						break;
				}
			}else{//ATTIVO
				switch (rand(1,2)) {
					case 1: //DECENNI
						$genere ="decades";
						break;
					case 2: //FOLK
						$genere ="roots";
						break;
				}
			}
			
		}
  	}else{
  		return getMusicByEmotion($resp,$parameters,$text,$email);
  	}

	

	//Prendo la playlist di quel genere
	$playlists = $api->getCategoryPlaylists(strtolower($genere), [
    	'country' => 'se',
	]);


	$nomiPlaylist = array(); 

	//Prendo tutte le playlist di quel genere
	foreach ($playlists->playlists->items  as $playlist) {
		$nomiPlaylist[] = $playlist;
	}

	//Numero casuale
	$num = rand(0,count($nomiPlaylist)-1);

	//Prendo la playlist corrispondente
	$playlist = $nomiPlaylist[$num];
	$playlistName = $playlist->name; //Nome playlist Spotify
	$url = $playlist->external_urls->spotify; //Url playlist Spotify

	/*
	Aggiungo alla url di Spotify la parola embed/ altrimenti l'iframe non verrà visualizzato per problemi di Copyright
	Esempio:
	https://open.spotify.com/track/2J9TGb5CRT4omfAgnKmXn5 ----> https://open.spotify.com/embed/track/2J9TGb5CRT4omfAgnKmXn5
	*/
	$answer = substr_replace($url, "embed/", 25, 0);

	return $answer;

}