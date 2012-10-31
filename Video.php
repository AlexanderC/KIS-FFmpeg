<?php

/**
 * FFmpeg Video wrapper class
 * @readme http://www.willus.com/author/streaming2.shtml
 *         https://gist.github.com/3986104
 * 
 * @author AlexanderC
 */

class Video {
    
    const SCREENSHOT_OFFSET = -4;
    const BINARY = 'ffmpeg';
    const THUMB_PROP = 200;
    
    /**
     * Video file
     * 
     * @var \SplFileInfo
     */
    private $file;
    
    /**
     * Raw Info data
     * 
     * @var array
     */
    private $rawInfo;
    
    /**
     * @param \SplFileInfo $file
     */
    public function __construct(\SplFileInfo $file) {
        $this->file = $file;
        $this->populateRawData();
    }
    
    /**
     * Transform video into binary readonly resource
     * 
     * @return resource
     */
    public function __toString() {
        return fopen((string) $this->file, "rb");
    }
    
    /**
     * Get video file
     * 
     * @return \SplFileInfo
     */
    public function getFile(){
        return $this->file;
    }
    
    /**
     * Encode video to another format by providing audio
     * & video codecs
     * 
     * @param string $audioCodec
     * @param string $videoCodec
     * @param string $extension
     * @param string $persistentPath
     * @param string $extraOptions
     * @param bool $strict
     * @return \self
     * @throws \RuntimeException
     */
    public function encodeIntoImplicit($audioCodec, $videoCodec, $extension, $persistentPath = NULL, $extraOptions = "", $strict = false){
        if(empty($persistentPath)){
            $newFile = tempnam(sys_get_temp_dir(), 'video_format_implicit_tmpfile_');
            rename($newFile, "{$newFile}.{$extension}");
            $newFile = new \SplFileInfo("{$newFile}.{$extension}");
        } else {
            $newFile = rtrim($persistentPath, '/') . "/" . md5($this->file) . self::replaceExtensionWith($this->file, $extension);
            
            // case persist and video exists already
            if(is_file($newFile)) return new self(new \SplFileInfo($newFile));
            
            $newFile = new \SplFileInfo($newFile);
        }
        
        $command = array(
            self::BINARY,
            "-y",
            '-i ' . escapeshellarg($this->file),
            ($strict ? "-strict experimental" : ""),
            $extraOptions,
            "-c:v " . escapeshellarg(strtolower($videoCodec)),
            "-c:a " . escapeshellarg(strtolower($audioCodec)),
            escapeshellarg($newFile)
        );
        
        exec(implode(' ', $command), $output, $success);
        
        if((int) $success === 1){
            @unlink($newFile);
            throw new \RuntimeException("Unable to convert video into '.{$extension}' using {$audioCodec}:{$videoCodec} -=[".implode(' ', $command)."]=-");  
        }
        
        return new self($newFile);
    }
    
    /**
     * Encode video into another format
     * 
     * @param string $format
     * @param string $persistentPath
     * @retrur \Video
     */
    public function encodeInto($format, $persistentPath = NULL){
        if(!in_array($format, self::getFFmpegDEformats())){
            throw new \RuntimeException("Unknown format '{$format}' provided");
        }
        
        if(empty($persistentPath)){
            $newFile = tempnam(sys_get_temp_dir(), 'video_format_tmpfile_');
            rename($newFile, "{$newFile}.{$format}");
            $newFile = new \SplFileInfo("{$newFile}.{$format}");
        } else {
            $newFile = rtrim($persistentPath, '/') . "/" . md5($this->file) . self::replaceExtensionWith($this->file, $format);
            
            // case persist and video exists already
            if(is_file($newFile)) return new self(new \SplFileInfo($newFile));
            
            $newFile = new \SplFileInfo($newFile);
        }
        
        $command = array(
            self::BINARY,
            "-y",
            '-i ' . escapeshellarg($this->file),
            escapeshellarg($newFile)
        );
        
        exec(implode(' ', $command), $output, $success);
        
        if((int) $success === 1){
            @unlink($newFile);
            throw new \RuntimeException("Unable to convert video into '{$format}'");
        }
        
        return new self($newFile);
    }
    
    /**
     * Get string for html5 video source attribute
     * 
     * @return string
     */
    public function getHtml5SourceTypeString(){
        return "video/" . mb_strtolower(pathinfo($this->file, PATHINFO_EXTENSION));
    }
    
    /**
     * Replace initial file extension with another thing
     * 
     * @param \SplFileInfo $file
     * @param string $extension
     * @retrun string
     */
    private static function replaceExtensionWith(\SplFileInfo $file, $extension){
        return preg_replace('/' . preg_quote(mb_strtolower(pathinfo($file, PATHINFO_EXTENSION)), '/') . '$/ui', $extension, $file->getBasename());
    }
    
    /**
     * Populate raw data
     * 
     * @return void
     */
    private function populateRawData(){
        $command = array(
            self::BINARY,
            "-i " . escapeshellarg((string) $this->file),
            "2>&1"
        );
        
        exec(implode(' ', $command), $this->rawInfo);
        $this->clearRawInfoData();
    }
    
    /**
     * Clear unnecessary raw info data
     * 
     * @return void
     */
    private function clearRawInfoData(){
        foreach($this->rawInfo as $key => $dataString){
            if(!preg_match('/^\s*(Duration:|Stream){1}.+/ui', $dataString)){
                unset($this->rawInfo[$key]);
            }
        } 
        
        $this->rawInfo = array_values($this->rawInfo);
        $this->rawInfo = array(
            $this->rawInfo[0], $this->rawInfo[1], $this->rawInfo[2],
        );
    }
    
    /**
     * Get audio bitrate in kb/s
     * 
     * @return int
     */
    public function getAudioBitrate(){
        if(preg_match('/,\s+(\d+)\s+kb\/s/ui', $this->rawInfo[2], $match)){
            return $match[1];
        }
        return 0;
    }
    
    /**
     * Check if audio track is stereo
     * 
     * @return bool
     */
    public function isStereo(){
        return (bool) preg_match('/,\s+stereo,/ui', $this->rawInfo[2]);
    }
    
    /**
     * Get start point in seconds
     * 
     * @return float
     */
    public function getStartPoint(){
        if(preg_match('/start:\s+([:\.\d]+),/ui', $this->rawInfo[0], $match)){
            return (float) $match[1];
        }
        return 0;
    }
    
    /**
     * Get audio sample rate in HZ
     * 
     * @return int
     */
    public function getSampleRate(){
        if(preg_match('/,\s+(\d+)\s+Hz,/ui', $this->rawInfo[2], $match)){
            return $match[1];
        }
        return 0;
    }
    
    /**
     * Get audio codec
     * 
     * @return string
     */
    public function getAudioCodec(){
        if(preg_match('/Audio:\s+([\w\d]+)(\s+\(.+)?,/ui', $this->rawInfo[2], $match)){
            return $match[1];
        }
        return 'unknown';
    }
    
    /**
     * Get FPS ratio
     * 
     * @return int
     */
    public function getFPS(){
        if(preg_match('/,\s+(\d+)\s+fps,/ui', $this->rawInfo[1], $match)){
            return $match[1];
        }
        return 0;
    }
    
    /**
     * Retrieve video codec
     * 
     * @return string
     */
    public function getVideoCodec(){
        if(preg_match('/Video:\s+([\w\d]+)(\s+\(.+)?,/ui', $this->rawInfo[1], $match)){
            return $match[1];
        }
        return 'unknown';
    }
    
    /**
     * Get video width in px
     * 
     * @return int
     */
    public function getWidth(){
        $resolution = $this->getResolution();
        return $resolution[0];
    }
    
    /**
     * Get video height in px
     * 
     * @return int
     */
    public function getHeight(){
        $resolution = $this->getResolution();
        return $resolution[1];
    }
    
    /**
     * Get resolution
     * 
     * @return array
     */
    public function getResolution(){
        if(preg_match('/,\s+([\dx]+)\s*(,|\[SAR)/ui', $this->rawInfo[1], $match)){
            return explode('x', $match[1]);
        }
        return array(0, 0);
    }
    
    /**
     * Get video duration in seconds
     * 
     * @return float
     */
    public function getDuration(){
        if(preg_match('/Duration:\s+([:\.\d]+),/ui', $this->rawInfo[0], $match)){
            $durationMap = explode(':', $match[1]);
            
            $duration = ((int) $durationMap[0]) * 60 * 60;
            $duration += ((int) $durationMap[1]) * 60;
            $duration += (float) $durationMap[2];
            return $duration;
        }
        return 0;
    }
    
    /**
     * Get video bitrate in kb/s
     * 
     * @return int
     */
    public function getVideoBitrate(){
        if(preg_match('/bitrate:\s+(\d+)\s+kb\/s/ui', $this->rawInfo[0], $match)){
            return $match[1];
        }
        return 0;
    }
    
    /**
     * Take an screenshot for the video
     * 
     * @param int $offset
     * @param bool $returnFile
     * @return string | \SplFileInfo
     */
    public function takeScreenshot($offset = self::SCREENSHOT_OFFSET, $returnFile = false){
        $tmpFile = tempnam(sys_get_temp_dir(), 'videoScreenshot_');
        
        $command = array(
            self::BINARY,
            "-itsoffset " . escapeshellarg((string) $offset),
            "-i " . escapeshellarg((string) $this->file),
            "-vframes 1",
            "-an",
            " -f image2",
            escapeshellarg($tmpFile),
            // case not return file
            $returnFile ? "" : "&& cat " . escapeshellarg((string) $this->file)
        );
        
        if(!$returnFile){
            exec(implode(' ', $command), $content);
            @unlink($tmpFile);
            return implode(PHP_EOL, $content);
        } else {
            exec(implode(' ', $command));
            
            return new \SplFileInfo($tmpFile);
        }
    }
    
    /**
     * Get video info
     * 
     * @return array
     */
    public function getRawInfo(){
        return $this->rawInfo;
    }
    
    /**
     * Get video thumbnail
     * 
     * @param int $prop
     * @param \Closure $callback
     * @return string
     */
    public function getThumbnail($prop = self::THUMB_PROP, \Closure $callback = NULL){
        $image = new Imagick((string) $this->takeScreenshot(self::SCREENSHOT_OFFSET, true));
        
        $imageprops = $image->getImageGeometry();
        if (!($imageprops['width'] <= (int) $prop && $imageprops['height'] <= (int) $prop)) {
            $image->thumbnailImage((int) $prop, (int) $prop, true);
        }
        
        if($callback instanceof \Closure){
            $content = $callback((string) $image);
        } else {
            $content = (string) $image;
        }
        
        $image->clear();
        $image->destroy();
        
        return $content;
    }
    
    /* ############################################################ */
    
    /**
     * All DE FFmpeg formats catched using
     * ffmpeg -formats | grep DE
     * 
     * @return array
     */
    public static function getFFmpegDEformats(){
        return array(
            "mov",
            "webm",
            "mpg",
            "mp4",
            "avi",
            "flac",
            "flv",
            "mpeg",
            "ogv",
            "swf",
            "wmv",
            "mkv",
            "3gp",
            "3g2",
            "amc",
            "h264",
            "m2p",
            "m4v",
            "moi",
            "mts",
            "vob",
            "xvid"
        );
    }
}