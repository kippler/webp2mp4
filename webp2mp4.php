<?php

/*
	webp2mp4.php
		2021/6/4 - park

	Usage:
		php -d disable_functions= webp2mp4.php src.webp dst.mp4

*/


function webp2png($src, $tmpdir)
{
	$cmd = sprintf("anim_dump -folder %s %s", $tmpdir, $src);
	printf("%s\n", $cmd);
	$result = exec($cmd, $output, $retval);
}

function getwebpinfo($src, &$width, &$height)
{
	$cmd = sprintf("webpmux -info %s", $src);
	printf("%s\n", $cmd);
	$result = exec($cmd, $output, $retval);
	/*
	$output ==>
Canvas size: 592 x 418
Features present: animation EXIF metadata
Background color : 0xFFFFFFFF  Loop Count : 0
Number of frames: 5
No.: width height alpha x_offset y_offset duration   dispose blend image_size  compression
  1:   592   418    no        0        0     1000 background    no       1224       lossy
  2:   592   418    no        0        0     2000 background    no       1904       lossy
  3:   592   418    no        0        0     3000 background    no       2380       lossy
  4:   592   418    no        0        0     4000 background    no       2392       lossy
  5:   592   418    no        0        0     5000 background    no       2968       lossy
Size of the EXIF metadata: 97
	*/
	$width = 0;
	$durations = array();
	foreach($output as $line)
	{
		$line = trim($line);
		if(is_numeric (substr($line, 0,1)))
		{
			sscanf($line, "%s %s %s %s %s %s %s %s %s %s %s", $a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10, $a11);
			array_push($durations, $a7);

			if($width==0)
			{
				$width = $a2;
				$height = $a3;
			}
		}
	}

	return $durations;
}


function write_concatfile($tmpdir, $durations)
{
	$concat_file_name = $tmpdir . "/concatfile.txt";
	$fp = fopen($concat_file_name, "w");
	if(!$fp)
	{
		echo "cannot open concatfile\n";
		die;
	}

	/*
	concatfile.txt ==>
		file dump_0000.png
		duration 0.1
		file dump_0001.png
		duration 0.2
		....
	*/

	$count = 0;
	foreach($durations as $duration)
	{
		$png_filename = sprintf("dump_%04d.png", $count++);

		fprintf($fp, "file %s\n", $png_filename);
		fprintf($fp, "duration %f\n", $duration / 1000.);	// ms to sec
	}
	fclose($fp);
	return $concat_file_name;
}

function clear_tmp($tmpdir, $concat_file_name)
{
	unlink($concat_file_name);

	$count = 0;
	for(;;)
	{
		$png_file_name = sprintf("%s/dump_%04d.png", $tmpdir, $count++);
		if(file_exists($png_file_name)==false)
			break;

		unlink($png_file_name);
	}
	rmdir($tmpdir);
}

function webp2mp4($src, $dst, $tmpdir)
{
	webp2png($src, $tmpdir);
	$width = 0; 
	$height = 0;
	$durations = getwebpinfo($src, $width, $height);
	$concat_file_name = write_concatfile($tmpdir, $durations);

	$ffmpeg_options = "-y -pix_fmt yuv420p ";

	// width must be divisible by 2
	if($width%2 || $height%2)
	{
		$ffmpeg_options = $ffmpeg_options . sprintf("-vf scale=%d:%d ", $width-$width%2, $height-$height%2 );
	}


	$cmd = sprintf("ffmpeg -f concat -i %s %s %s", $concat_file_name, $ffmpeg_options, $dst);
	printf("%s\n", $cmd);
	$result = exec($cmd, $output, $retval);
	clear_tmp($tmpdir, $concat_file_name);
}


function main()
{
	$count = $_SERVER['argc'];
	if($count !=3)
	{
		printf("Usage: php -d disable_functions= webp2mp4.php src.webp dst.mp4\n");
		return;
	}

	$src = $_SERVER['argv'][1];
	$dst = $_SERVER['argv'][2];
	$tmpdir = "tmp";
	if(file_exists($tmpdir)==false)
		mkdir($tmpdir);
	if(file_exists($tmpdir)==false)
	{
		echo "error. tmp dir is not exist\n";
		return;
	}

	webp2mp4($src, $dst, $tmpdir);
}


main();


?>