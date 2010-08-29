<?php

class Images {

    public static function resizeSquare($in, $out, $size) {
        $img = new Imagick($in);

        $w = $img->getImageWidth();
        $h = $img->getImageHeight();
        $min = min($w, $h);
        $img->cropImage($min, $min, 0, 0);
        $img->resizeImage($size, $size, imagick::FILTER_CUBIC, 1);

        $img->writeImage($out);

        return $out;
    }

    public static function getFileExt($fname) {
        $parts = explode('.', $fname);
        end($parts);
        return current($parts);
    }

    public static function makeUserpics($id, $big_picture, $out_dir) {
        $ext = Images::getFileExt($big_picture);
        $i180 = Images::resizeSquare($big_picture, "$out_dir/180h_$id.$ext", 180);
        $i100 = Images::resizeSquare($big_picture, "$out_dir/100h_$id.$ext", 50);
        $i36 = Images::resizeSquare($big_picture, "$out_dir/small_$id.$ext", 20);

        return array(basename($big_picture), basename($i180), basename($i100), basename($i36));
    }
};
