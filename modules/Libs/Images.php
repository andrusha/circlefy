<?php

class Images {

    public static function resizeSquare($in, $out, $size) {
        $img = new Imagick($in);

        $w = $img->getImageWidth();
        $h = $img->getImageHeight();
        $min = min($w, $h);
        $img->cropImage($min, $min, 0, 0);
        $img->resizeImage($size, $size, imagick::FILTER_LANCZOS, 1);

        @unlink($out);
        $img->writeImage($out);

        return $out;
    }

    public static function getFileExt($fname) {
        $parts = explode('.', $fname);
        end($parts);
        return current($parts);
    }

    /*
        Makes userpics for user/group
    */
    public static function makeUserpics($id, $big_picture, $out_dir) {
        $ext = Images::getFileExt($big_picture);
        $large = Images::resizeSquare($big_picture, "$out_dir/large_$id.$ext", 180);
        $medium = Images::resizeSquare($big_picture, "$out_dir/medium_$id.$ext", 50);
        $small = Images::resizeSquare($big_picture, "$out_dir/small_$id.$ext", 20);

        return array(basename($big_picture), basename($large), basename($medium), basename($small));
    }

    /*
     * Download and make avatars
     *
     * @params $link string if specified used to fetch favicon
     * @returns array
    */
    public static function fetchAndMake($picsDir, $picUrl, $picName = null) {
        if ($picName === null) {
            $ext = Images::getFileExt($picUrl);
            if (empty($ext) || strlen($ext) > 5)
                $ext = 'jpg';

            $picName = tempnam($picsDir, $ext);
        }
        $picName = $picsDir.'/'.$picName;

        $curl = new Curl();
        $curl->saveFile($picUrl, $picName);

        $id = basename($picName, '.'.Images::getFileExt($picName));
        $result = Images::makeUserpics($id, $picName, $picsDir);

        return $result;
    }

    public static function getFavicon($url, $name) {
        preg_match('#^(http://)?(.*?)(/.*)?$#ism', $url, $m);
        $base = $m[2];
        if (empty($base))
            return null;

        $url = "http://$base/favicon.ico";
        $curl = new Curl();
        $curl->saveFile($url, $name);

        return basename($name);
    }
};
