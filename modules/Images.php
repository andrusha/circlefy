<?php

class Images {

    public static function resizeSquare($in, $out, $size) {
        $img = new Imagick($in);

        $w = $img->getImageWidth();
        $h = $img->getImageHeight();
        $min = min($w, $h);
        $img->cropImage($min, $min, 0, 0);
        $img->resizeImage($size, $size, imagick::FILTER_LANCZOS, 1);

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
        $i180 = Images::resizeSquare($big_picture, "$out_dir/180h_$id.$ext", 180);
        $i100 = Images::resizeSquare($big_picture, "$out_dir/100h_$id.$ext", 50);
        $i36 = Images::resizeSquare($big_picture, "$out_dir/small_$id.$ext", 20);

        return array(basename($big_picture), basename($i180), basename($i100), basename($i36));
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

        file_put_contents($picName, file_get_contents($picUrl));

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
        file_put_contents($name, file_get_contents($url));

        return basename($name);
    }
};
