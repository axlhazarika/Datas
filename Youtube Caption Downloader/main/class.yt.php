<?php

class caption
{
	public $video_id;
	public $lang;
	
	function __construct($video_id, $lang) {
		$this->video_id = $video_id;
		$this->lang = $lang;
	}
	
	public function cleantext($content) {	
	    $isupper = $content[4] === strtoupper($content[4]) ? true : false;
		if(!$isupper) $content = $this->punctuate($content);	
		
		return $content;
	}
	
	public function grammar($content) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,"https://app.contentgorilla.co/api/correct-grammar");
        curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "inputText=$content");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       
		$server_output = curl_exec($ch);
        curl_close ($ch);
		
		return $server_output;
	}
	
	public function punctuate($content) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,"https://app.contentgorilla.co/api/punctuate");
        curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "text=$content");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       
		$server_output = curl_exec($ch);
        curl_close ($ch);
		
		return $server_output;
	}
	
	public function getCaption()
	{
		$captions = $this->getCaptionsList($this->video_id);
		$caption_url = '';

		foreach ($captions['translationLanguages'] as $language) {
			if ($language['languageCode'] == $this->lang) {
				$caption_url = $language['url'];
				break;
			}
		}

		$request = file_get_contents($caption_url);
		
		$captions_array = simplexml_load_string($request);
		$captions = '';

		foreach ($captions_array as $line) {
			if (!is_array($line)) {
				$captions .= ' ' . $line;
			}
		}

		$data = $this->convertToParagraphs($captions);
	}

	public function getCaptionsList($video_id)
	{
		$request = file_get_contents('https://www.youtube.com/get_video_info?&video_id=' . $video_id . '&lang=en');
		parse_str($request, $video_info_array);

		if (!property_exists(json_decode($video_info_array['player_response']), 'captions') && !isset(json_decode($video_info_array['player_response'])->captions)) {
			$langs['captions'][0] = '';
			$langs['translationLanguages'][0] = '';
			return $langs;
		}

		$languages_info = json_decode($video_info_array['player_response'])->captions->playerCaptionsTracklistRenderer;
		$langs = array();

		foreach ($languages_info->captionTracks as $key => $lang) {
			$langs['captions'][$key]['name'] = $lang->name->simpleText;
			$langs['captions'][$key]['languageCode'] = $lang->languageCode;
			$langs['captions'][$key]['url'] = $lang->baseUrl;
		}

		foreach ($languages_info->translationLanguages as $key => $trans) {
			$langs['translationLanguages'][$key]['name'] = $trans->languageName->simpleText;
			$langs['translationLanguages'][$key]['languageCode'] = $trans->languageCode;
			$langs['translationLanguages'][$key]['url'] = $langs['captions'][0]['url'] . '&tlang=' . $trans->languageCode;
		}

		return $langs;
	}

	public function convertToParagraphs($captions, $fix = false)
	{
		$captions_array = explode('. ', $captions);
		$captions_new_array = array();
		$temp = '';

		$random_word_count = rand(60, 200);
		$word_count = 0;
        $c = 0;
		
		foreach ($captions_array as $caption) {
			$word_count += str_word_count(strip_tags($caption));
			
			if ($word_count >= $random_word_count) {
				$captions_new_array[] = "<p>" . $temp . $caption . "</p>";
				$temp = '';
				$word_count = 0;
				$random_word_count = rand(60, 200);
				
				$c++;
			} else {
				$temp .= $caption . '. ';
			}
		}
												
		if (!empty($temp)) $captions_new_array[] = str_replace('. .', '.', $temp);
		
		$cap  = implode('.<br><br>', $captions_new_array);
		$cap =  html_entity_decode($cap, ENT_QUOTES);
		$cap = $this->cleantext($cap);
		
		if($c == 1) {
		  $this->convertToParagraphs($cap, true);
		  return;
		}
	    
		if($fix) $cap = $this->grammar($cap);
		
		file_put_contents('./para.html', $cap);
		echo 'done';
	}
}