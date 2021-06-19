<?php

/*
* ### Description: 
* Tool to help create video clips for expressions or gifs with sound. It takes a video
* and subtitles file and smartly cuts tiny clips that can be used to express what you're
* feeling in a moment. It works with any video format and accepts .srt subtitles.
* 
* ### Why I wrote this:
* I always felt that despite being so powerful, GIFs lost great impact when muted. 
* I wanted to fix that. I wanted to create a gifs with sound utility.

* ### What's interesting:
* It is tricky to cut videos based on subtitles even though it didn't seem like that when I 
* started. You have to take care of sentence lines. You have to be aware of context of sentence.
* You also need to cut in a way so output isn't corruputed and doesn't contain segment of next or
* previous scene. All in all, this was a really fun problem to solve. Even though this project never
* went anywhere, it still lives in my Github and brings back fun memories. 
* 
*/

require('../../vendor/autoload.php');
use Benlipp\SrtParser\Parser;

class Expresion
{
	// These methods make sure config values aren't all over the class
	public $filename_length = 40;
	public $filename_space_separator = '-';
	public $maximum_line_length = 35;
	public $ffmpeg = 'ffmpeg';
	public $video_codec = 'copy';
	public $audio_codec = 'copy';

	public function __construct($video, $subtitles, $output_directory) {
		$this->video = $video;
		$this->subtitles = $subtitles;
		$this->output_directory = $output_directory;
		$this->filename_space_separator = '-';
	}

	/* Configuration get and set methods start */
	public function set_video_codec($codec) {
		$this->video_codec = $video_codec;
	}

	public function get_video_codec($codec) {
		return $this->audio_codec;
	}

	/* Configuration get and set methods end */

	/**
	* When cutting video based on subtitles, it often outputs a file that has cut words 
	* This function adds a tiny bit of adding at the start to ensure output video is perfect
	* and file isn't corrupted. These values have been picked after 100's of tests 
	* @author: Saqib Razzaq
	* @param: { $duration } { integer, float } { total duration of the video }
	* @return: { float } { padding to add based on duration }
	*/
	private static function get_padding($duration) {
		return $duration <= 1.10 ? 0.35 : 0.20;
	}

	/**
	* We want to make sure that we cut one liners, the clips that can express something.
	* Ideal clips are generic that can fit any situation. Some examples are:
	* #1: What?
	* #2: Who?
	* #3: What are you doing?
	* #4: You are crazy!
	* #5: I'm done.
	* #6: Run!
	* 
	* This function returns some of most used end characters. This is subject to more additions
	* as the user sees fit based on their own needs.
	* @author: Saqib Razzaq
	*/
	private static function get_endchars() {
		return array('.', '!', '?');	
	}

	/**
	* Clean up a given line and remove some characters
	* @author: Saqib Razzaq
	* @param: { $line } { string } { text to be cleaned }
	* @return: { string } { a cleaned trimmed string }
	*/
	private static function cleanup($line) {
		return trim(str_replace(["\n", '<i>', '</i>'], ' ', $line));	
	}

	/**
	* Remove if a file exists
	* @author: Saqib Razzaq
	* @param: { $filepath } { string } { file to be removed }
	* @return: { void }
	*/
	private static function remove_existing($filepath) {
		if (file_exists($filepath)) { 
			@unlink($filepath); 
		}
	}

	/**
	* Create output filename based on contents of the line
	* @author: Saqib Razzaq
	* @param: { $line } { string } { line to create file for }
	* @return: { string } { cleaned and formatted filename }
	*/
	private function create_filename($line) {
		$line = substr(strtolower($line), 0, $this->filename_length);
		$line = str_replace(array('!', '$', '#', '?', '/', '*', "'", '.', ','), '', $line);
		$line = str_replace(' ', $this->filename_space_separator, $line);
		return sprintf("%s{$this->filename_space_separator}%s.mp4", $line, time());
	}

	/**
	* This is a really crucial function. It decides what lines should be skipped to make sure
	* output data is as useful as it can be. It skips music scenes, lines longer than specified
	* length, lines in middle of sentences and more
	* @author: Saqib Razzaq
	* @param: { $text } { string } { text to be validated }
	* @return: { boolean }
	*/
	private function validate_text($text) {
		// skip music scenes e.g ♪ if this is the end of the world ♪
		if (strstr($text, '♪')) {
			return;
		}

		// skip longer lines
		if (strlen($text) > $this->maximum_line_length) {
			return;
		}

		// skip lines in middle of sentences e.g were running away from him but
		if (!ctype_upper($text[0])) {
			return;
		}

		// skip lines not ending with these characters
		if (!in_array($text[-1], self::get_endchars())) {
			return;
		}

		return true;
	}

	/**
	* Processes a subtitles file and generates commands to create output files
	* @author: Saqib Razzaq
	* @return: { $commands } { array } { a list of commands for cutting clips }
	*/
	public function get_commands() {
		$parser = new Parser();
		$parsed = $parser->loadFile($this->subtitles)->parse();
		$commands = [];

		foreach ($parsed as $current_line) {
			$current_text = self::cleanup($current_line->text);

			if ($this->validate_text($current_text)) {
				$duration = $current_line->endTime - $current_line->startTime;
				$padding = self::get_padding($duration);
				$start = $current_line->startTime - $padding;

				$output_file = $this->create_filename($current_text);
				self::remove_existing($output_file);

				$total_time = $duration + $padding;
				$command = "$this->ffmpeg -ss {$start} -i {$this->video} -t {$total_time} -vcodec {$this->video_codec} -acodec {$this->audio_codec} -y {$this->output_directory}/{$output_file}";
				$commands[] = $command;
			}
		}

		return $commands;
	}

	/**
	* Creates clips based on commands provided and stores logs
	* @author: Saqib Razzaq
	* @param: { $commands } { array } { list of commands to process }
	* @return: { $response } { array } { a list with command and their respective log file  }
	*/
	public function process($commands) {
		$response = array();
		$logs_directory = $this->output_directory . '/logs/';
		if (!file_exists($logs_directory)) {
			@mkdir($logs_directory, 777);
		}

		foreach ($commands as $key => $command) {
			$log_file = $logs_directory . date("Y-m-d") . $this->filename_space_separator . md5(uniqid(rand(), true)); // just make it random
			$output = shell_exec("{$command} 2>&1");
			file_put_contents("{$log_file}.log", $output)
			$response[$command] = $log_file;
		}

		return $response;
	}
}