<?php

/*
    Collection of text, date functions etc
    not hard linked to model stuff
*/
abstract class FuncLib {
    /*
        Makes text shorter. If it exceeds limit, adds '...'
    */
    public static function makePreview($text, $limit = 50) {
        if (!function_exists('clean')) {
            //cleans all mess around text, before make it shorter
            function clean($text) {
                $text = trim($text);
                $text = preg_replace('/\s{2,}/ism', ' ', $text);
                return $text;    
            }
        }

        if (!function_exists('byExplode')) {
            //tries to combine as much parts of string
            //exploded by delimeter not to exceed limit
            function byExplode($text, $delimeter, $limit) {
                $parts = explode($delimeter, $text);
                $result = '';
                foreach ($parts as $part) {
                    if (strlen($result) + strlen($delimeter) + strlen($part) <= $limit)
                        $result .= $delimeter.$part;
                }
                $result = substr($result, 1);

                return $result;
            }
        }

        $text = clean($text);

        //okay, text is good enough anyway
        if (strlen($text) <= $limit)
            return $text;
        
        $limit -= 4; //for ' ...'
        $byPoint = byExplode($text, '.', $limit);
        $byWord = byExplode($text, ' ', $limit);

        $result = '';

        //select best match, if fails, just
        //cut text up to the limit
        if ($byPoint || $byWord) {
            if (strlen($byPoint) > strlen($byWord))
                $result = $byPoint;
            else
                $result = $byWord;
        } else {
            $result = substr($text, 0, $limit-1);
        }

        return $result.' ...';
    }

    /*
        Replaces all looks-like links/emails
        in text with html-tags
    */
    public static function linkify($str) {
        $str = preg_replace("/\\b([a-z\\.-_]*@[a-z\\.]*\\.[a-z]{1,6})\\b/i", "<a href='mailto:\\1' target='_blank'>\\1</a>", $str);
        $str = preg_replace('#\b((?:[a-z]{1,5}://|www\.)([a-z0-9\-\.]*\.[a-z]{1,6}(?:[a-z_\-0-9/\#=\?]*)))\b#i', "<a href='\\1' target='_blank'>\\2</a>", $str);
        return $str;
    }

    /*
        Returns well formatted time since
        from unix timestamp
    */
    public static function timeSince($timestamp) {
        $diff = time() - $timestamp;
        $days = floor($diff / (60*60*24));
        $date = date("jS M Y", $timestamp);
       
        if ($days == 0 || $diff <= 0) {
            if ($diff < 120) {
                $date = "Just Now";
            } elseif ($diff < 60*60) {
                $mins = floor($diff / 60);
                $date = "$mins mins ago";
            } elseif ($diff < 60*60*2) {
                $date = "An hour ago";
            } elseif ($diff < 60*60*24) {
                $hours = floor($diff / (60*60));
                $date = "$hours hours ago";
            }
        } elseif ($days == 1) {
            $date = "Yesterday";
        } elseif ($days < 7) {
            $date = "$days days ago";
        } elseif ($days == 7) {
            $date = "A week ago";
        } elseif ($days < 31) {
            $weeks = ceil($days / 7);
            $date = "$weeks weeks ago";
        }

        return $date;
    }
   
    /*
        Try to reduce phrase down to symbol limit,
        saving as much sense as possible
    */
    public static function makeSymbol($gname, $limit = 64) {
        //make words in Camel Case, if there is words
        if (strpos($gname, ' ') !== false) {
            $gname = ucwords(strtolower($gname));
            $gname = str_replace(' ', '-', trim($gname));
        }

        //delete all garbage symbols
        $gname = preg_replace('/[^a-z0-9\-)(]*/i', '', $gname);
        
        //if gname length is greater, than DB limit
        //try to make abbreviation of our words,
        //if words at least two
        if (strpos($gname, '-'))
            while(strlen($gname) > $limit && preg_match('/[a-z]/', $gname))
                $gname = preg_replace('/([A-Z])[a-z0-9]+([^a-z0-9]*)$/', '$1$2', $gname);
        
        //if even after abbreveation name is greater
        //our limit, then just cut it
        $gname = substr($gname, 0, $limit);

        return $gname;
    }

    public static function addPrefix($prefix, array $strings) {
        return array_map(function ($x) use ($prefix) { return "$prefix$x"; }, $strings);
    }

    /*
        Assuming, that every <a <b <i tags have keyphrases in it
        extract from html-formatted text as much tags as we can
    */
    public static function extractTags($gname, $descr, $category, $limit = 10, $size_limit = 128) {
        $tags = array();

        //category is tag anyway
        $tags[] = $category;

        //now, let us extract all stuff <b>, <i> & <a> tags
        //this are usually somewhat realted to
        $result = array();
        $count = 0;
        preg_match_all('/<(?P<tagname>[abi])(?:\s[^>]*?)?>(?P<info>.*?)<\/(?P=tagname)>/i', $descr, $result, PREG_PATTERN_ORDER);
        foreach ($result['info'] as $tag) {
            $tag = trim($tag);
            if (strlen($tag) < $size_limit) {
                $tags[] = $tag;
                $count++;
                if ($count > $limit)
                    break;
            }
        }
        
        $tags = array_unique($tags);
        return $tags;
    }
};
