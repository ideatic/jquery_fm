<?php

abstract class FileManagerHelper
{


    public static function format_number($number, $decimals = 0)
    {
        $locale = localeconv();

        $dec_point = $locale['decimal_point'];
        $thousands_sep = $locale['thousands_sep'];


        return rtrim(number_format($number, $decimals, $dec_point, $thousands_sep), "0$dec_point");
    }

    public static function format_size($size, $kilobyte = 1024, $format = '%size% %unit%')
    {
        if ($size < $kilobyte) {
            $unit = 'bytes';
        } else {
            $size = $size / $kilobyte;
            $units = array('KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
            foreach ($units as $unit) {
                if ($size > $kilobyte) {
                    $size = $size / $kilobyte;
                } else {
                    break;
                }
            }
        }

        return strtr(
            $format,
            array(
                '%size%' => self::format_number($size, 2),
                '%unit%' => $unit
            )
        );
    }

    public static function recursive_delete($directory, $delete_dirs = true)
    {
        if (!is_dir($directory)) {
            return false;
        }

        $dirh = opendir($directory);
        if ($dirh == false) {
            return false;
        }

        while (($file = readdir($dirh)) !== false) {
            if ($file[0] != '.') {
                $path = $directory . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    if ($delete_dirs) {
                        self::recursive_delete($path, $delete_dirs);
                    }
                } else {
                    unlink($path);
                }
            }
        }
        closedir($dirh);
        return rmdir($directory);
    }
}