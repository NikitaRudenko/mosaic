<?php

class Mosaic {

	public $insertImagesSource;
	public $sourceImage;
	public $readyImage;

	private $allowableExtensions = ['jpg', 'jpeg', 'png'];
	
	private $insertImagesSmallSource;
	private $insertImagesSmall = array();
	public $step;
	public $smallImgWidth;
	public $smallImgHeight;
	public $useSource;
	
	private $insertFiles = array();
	private $insertImages = array();


	public function __construct($insertImagesSource, $sourceImage, $readyImage, $step = 5, $smallImgsSize = [100,100], $useSource = false){
		$this->insertImagesSource = $insertImagesSource;
		$this->sourceImage = $sourceImage;
		$this->readyImage = $readyImage;
		$this->useSource = $useSource;
		$this->step = $step;
		list($this->smallImgWidth, $this->smallImgHeight) = $smallImgsSize;
	}

	public function handlingInsertImages(){
		if($this->useSource){
			$this->insertFiles[] = $this->sourceImage;
		}
		else{
			$files = scandir($this->insertImagesSource);
			$source = $this->insertImagesSource;
			array_walk($files, function(&$img) use ($source) {
				$img = $source.'/'.$img;
			});
			$this->insertFiles = $files;
		}
		$this->insertImages = $this->getOnlyImages($this->insertFiles);
		$this->insertImagesSmall = $this->resizeImages($this->insertImages);
		$this->insertImagesSmall = $this->getAverageColor($this->insertImagesSmall);
	}

	public function scanSourceImage(){
		$size = getimagesize($this->sourceImage);
		$img = imagecreatefromjpeg($this->sourceImage);
		$colors = array();

		$readyImage = imagecreatetruecolor(($size[0] / $this->step) * $this->smallImgWidth, ($size[1] / $this->step) * $this->smallImgHeight);
		// $readyImage = imagecreatetruecolor($size[0] * 2, $size[1] * 2);
		
		$X = $Y = 0;
		for ($x = 0; $x < $size[0]; $x += $this->step) {
			for ($y = 0; $y < $size[1]; $y += $this->step) {
				$color = imagecolorat($img, $x, $y);
				$rgb = imagecolorsforindex($img, $color);

				$insertImage = $this->getImageForPixel($rgb);

				imagecopymerge($readyImage, $insertImage, $X, $Y, 0, 0, $this->smallImgWidth, $this->smallImgHeight, 100);
				
				$Y = $Y + $this->smallImgHeight;
			}
			$Y = 0;
			$X = $X + $this->smallImgWidth;
		}
		imagejpeg($readyImage, $this->readyImage);
		imagedestroy($readyImage);
		return;
	}

	private function getImageForPixel($curRGB){
		if($this->useSource){
			$overlay  = imagecreatetruecolor($this->smallImgHeight, $this->smallImgWidth);
			$overlay_col = imagecolorallocatealpha($overlay, $curRGB['red'], $curRGB['green'], $curRGB['blue'], 100);
			imagefilledrectangle($overlay, 0, 0, $this->smallImgHeight, $this->smallImgWidth, $overlay_col);
			$source = $this->insertImagesSmall['source'];
			$insertImage = imagecreatefromjpeg($source['origin']);
			imagecopymerge($insertImage, $overlay, 0, 0, 0, 0, $this->smallImgWidth, $this->smallImgHeight, 70);
			$name = time().mt_rand().'.jpg';
			imagejpeg($insertImage, $this->insertImagesSmallSource.$name);
			$insertImage = imagecreatefromjpeg($this->insertImagesSmallSource.$name);
		}
		else{
			$result = array();
			foreach ($this->insertImagesSmall as $name => $data) {
				$ratio = abs($curRGB['red'] - $data['r']) + abs($curRGB['green'] - $data['g']) + abs($curRGB['blue'] - $data['b']);
				$result[$ratio] = $data;
			}
			ksort($result);
			$result = array_slice($result, 0, 15, true);
			$randKey = array_rand($result);
			$insertImage = imagecreatefromjpeg($result[$randKey]['origin']);
		}
		return $insertImage;
	}

	private function getOnlyImages($files){
		$result = array();
		foreach ($files as $i => $file) {
			$info = pathinfo($file);
			if(in_array($info['extension'], $this->allowableExtensions)){
				$result[$info['filename']]['origin'] = $info['dirname'].'/'.$info['basename'];
				$result[$info['filename']]['extension'] = $info['extension'];
			}
		}
		if(empty($result)) throw new Exception("No images found in dir: ".$this->insertImagesSource, 1);
		return $result;
	}

	private function resizeImages($bigImgs){
		$this->insertImagesSmallSource = $this->insertImagesSource.'/small/';
		if(!file_exists($this->insertImagesSmallSource)){
			mkdir($this->insertImagesSmallSource);
		}
		foreach ($bigImgs as $name => $img) {
			list($width, $height) = getimagesize($img['origin']);
			$imgSmall = imagecreatetruecolor($this->smallImgWidth, $this->smallImgHeight);
			$imgBig = imagecreatefromjpeg($img['origin']);
			imagecopyresampled($imgSmall, $imgBig, 0, 0, 0, 0, $this->smallImgWidth, $this->smallImgHeight, $width, $height);
			imagejpeg($imgSmall, $this->insertImagesSmallSource.$name.'.jpg', 100);
			$result[$name]['origin'] = $this->insertImagesSmallSource.$name.'.jpg';
		}
		return $result;
	}

	private function getAverageColor($smallImgs){
		foreach ($smallImgs as $name => $image) {
			$size = getimagesize($image['origin']);
			$img = imagecreatefromjpeg($image['origin']);
			$colors = array();
			
			for ($x = 0; $x < $size[0]; $x += 2) {
				for ($y = 0; $y < $size[1]; $y += 2) {
					$color = imagecolorat($img, $x, $y);
					$rgb = imagecolorsforindex($img, $color);

					$red[] = $rgb['red'];
					$green[] = $rgb['green'];
					$blue[] = $rgb['blue'];
				}
			}
			
			$avRed = array_sum($red) / count($red);
			$avGreen = array_sum($green) / count($green);
			$avBlue = array_sum($blue) / count($blue);
			
			$thisRGB = sprintf('%02X%02X%02X', $avRed, $avGreen, $avBlue);
			$smallImgs[$name]['avColor'] = $thisRGB;
			$smallImgs[$name]['r'] = $avRed;
			$smallImgs[$name]['g'] = $avGreen;
			$smallImgs[$name]['b'] = $avBlue;
			unset($red, $green, $blue);
		}
		return $smallImgs;
	}
}