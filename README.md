# Expresión
Express yourself with a clip - A smart video + srt to one liner clip generation utility 

Expresión is a tool to help create video clips for expressions or gifs with sound. It takes a video
and subtitles file and smartly cuts tiny clips that can be used to express what you're
feeling in a moment. It works with any video format and accepts .srt subtitles.

**Why I wrote this**
I always felt that despite being so powerful, GIFs lost great impact when muted. 
I wanted to fix that. I wanted to create a gifs with sound utility. This is what I have at the moment.

**Installation**
```
git clone https://github.com/sakydev/expresion.git
cd expresion.git
composer install
```

**USage**

```php
$video = 'input.mp4'; // full path to input video
$subtitles = 'input.srt'; // full path to substitles video (.srt)
$output_directory = './output'; // full path to output directory

$soundgifs = new Expression($video, $subtitles, $output_directory);
$commands = $soundgifs->get_commands(); // generates a list of commands to create clips
$soundgifs->process($commands); // pass all or any chunk of $commands to create videos 
```