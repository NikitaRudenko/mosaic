<?php
include './lib/mosaic.php';

$mosaic = new Mosaic('C:\OpenServer\domains\mosaic\images\insertImages', 'C:\OpenServer\domains\mosaic\images\sourceImages\source.jpg', 'C:\OpenServer\domains\mosaic\images\readyImages\ready.jpg');
$mosaic->handlingInsertImages();
$mosaic->scanSourceImage();
