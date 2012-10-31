In order to use this class you may have php 5.3 or higher, ffmpeg lib installed and an unix system.

**Basic usage:** 

```
$video = new Video(new \SplFileInfo($video_file_full_path));
$anotherVideo = $video->encodeInto("flv");

echo "WxH: {$anotherVideo->getWidth()}x{$anotherVideo->getWidth()}";

print_r($video->getRawInfo());
```
