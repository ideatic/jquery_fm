<?php

/**
 * Implements a file provider that uses the local file system
 */
class jQueryFM_FileProvider_FS extends jQueryFM_FileProvider_Base
{

    /**
     * Local path used by this provider as base path
     * @var string
     */
    public $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Get the real FS path for the given virtual folder
     *
     * @param $folder
     *
     * @return string
     */
    protected function _get_folder_path($folder)
    {
        if ($this->manager->allow_folders && $folder != '/' && $folder != '') {
            $folder = str_replace("\x00", '', (string)$folder); //Protect null bytes (http://www.php.net/manual/en/security.filesystem.nullbytes.php)
            $folder = str_replace('..', '', $folder); //Protect relative paths
            $real_folder = $this->path . DIRECTORY_SEPARATOR . $folder;
        } else {
            $real_folder = $this->path;
        }

        return $real_folder;
    }

    /**
     * Create a FileManagerItem given a path. This function can be overriden to customize the displayed information
     * @return FileManagerItem
     */
    protected function _populate_file_item($path, $folder)
    {
        $item = new FileManagerItem();
        $item->path = $path;
        $item->folder = $folder;
        $item->name = basename($path);
        $item->is_folder = is_dir($path);
        if ($item->is_folder) {
            if (!$this->manager->allow_folders) {
                return false;
            }
            $item->info = str_replace('%', max(0, iterator_count(new DirectoryIterator($path)) - 2), $this->manager->strings['number_files']);
        } else {
            $item->size = filesize($path);
            $item->info = jQueryFM_Helper::format_size($item->size);

            //Extract preview
            if ($this->manager->image_preview_limit < 0 || $item->size < $this->manager->image_preview_limit) {
                $extension = strtolower(pathinfo($item->name, PATHINFO_EXTENSION));
                if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'))) {
                    //Inline icon (all images in one request, but disable cache)
                    //$item->icon = "data:image/$extension;base64," . base64_encode(file_get_contents($item->path));

                    $item->icon = $this->manager->ajax_endpoint . (strpos($this->manager->ajax_endpoint, '?') !== false ? '&' : '?') . http_build_query(
                            array(
                                'action' => 'show',
                                'file' => $item->name,
                                'folder' => $item->folder
                            )
                        );
                }
            }
        }


        return $item;
    }

    private function _clean_filename($name)
    {
        return preg_replace("/[^a-zA-Z0-9\-\_\ \.]+/", '', $name);
    }

    /**
     *  {@inheritDoc}
     */
    public function read($folder = '/', $filter = '*')
    {
        $path = $this->_get_folder_path($folder);

        $folders = array();
        $files = array();
        if (is_dir($path)) {
            if (($handle = opendir($path)) !== false) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != '.' && $entry != '..' && fnmatch($filter, $entry)) {
                        $file = $this->_populate_file_item($path . DIRECTORY_SEPARATOR . $entry, $folder);
                        if ($file) {
                            if ($file->is_folder) {
                                $folders[] = $file;
                            } else {
                                $files[] = $file;
                            }
                        }
                    }
                }
                closedir($handle);
            } else {
                throw new RuntimeException("Path '$this->path' is not readable");
            }
        }

        return array_merge($folders, $files);
    }

    /**
     *  {@inheritDoc}
     */
    public function create_file($folder, $name, $tmp_path)
    {
        $folder_path = $this->_get_folder_path($folder);

        if (!is_dir($folder_path)) {
            if (!$folder_path || !mkdir($folder_path, 0755, true)) {
                throw new RuntimeException("Destination path '$folder_path' cannot be created");
            }
        }

        //Look for empty path
        $file_name = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $i = 0;
        do {
            $path = $folder_path . DIRECTORY_SEPARATOR . $this->_clean_filename($file_name) . ($i == 0 ? '' : " ($i)");
            if ($extension) {
                $path .= '.' . $extension;
            }

            $i++;
        } while (file_exists($path));

        if (move_uploaded_file($tmp_path, $path)) {
            return $this->_populate_file_item($path, $folder);
        }
        return false;
    }

    /**
     *  {@inheritDoc}
     */
    public function create_folder($folder, $name)
    {
        //Sanitize destination file
        $real_path = $this->_get_folder_path($folder);

        if ($real_path) {
            $path = $real_path . DIRECTORY_SEPARATOR . $this->_clean_filename($name);

            if (!is_dir($path) && mkdir($path, 0755, true) && ($path = realpath($path))) {
                return $this->_populate_file_item($path, $folder);
            }
        }

        return false;
    }

    /**
     *  {@inheritDoc}
     */
    public function rename(FileManagerItem $file, $new_folder, $new_name)
    {
        //Sanitize destination file
        $dest_folder = $this->_get_folder_path($new_folder);
        $dest_file = $dest_folder . DIRECTORY_SEPARATOR . $this->_clean_filename($new_name);
        $moving = dirname($file->path) != $dest_folder;

        if ($moving && !is_dir($dest_folder)) {
            //Create destination directory
            if (!mkdir($dest_folder, 0755, true)) {
                return false;
            }
        }

        if (file_exists($file->path) && !file_exists($dest_file) && rename($file->path, $dest_file)) {
            //If directory changes, return directory info
            if ($moving) {
                return $this->_populate_file_item($dest_folder, $file->folder);
            } else {
                return $this->_populate_file_item($dest_file, $file->folder);
            }
        }
        return false;
    }

    /**
     *  {@inheritDoc}
     */
    public function delete(FileManagerItem $file)
    {

        if ($file->is_folder) {
            return is_dir($file->path) && jQueryFM_Helper::recursive_delete($file->path);
        } else {
            return file_exists($file->path) && unlink($file->path);
        }
    }

    /**
     *  {@inheritDoc}
     */
    public function download(FileManagerItem $file, $force = true)
    {
        if (!file_exists($file->path)) {
            return false;
        }

        if ($force) {
            header("Content-Type: application/octet-stream");
            header('Content-Disposition: attachment; filename=' . $file->name);
        } else {
            $mime = jQueryFM_Helper::ext2mime(pathinfo($file->name, PATHINFO_EXTENSION));
            header("Content-Type: $mime");
            header('Content-Disposition: inline; filename=' . $file->name);
        }
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $file->size);

        //Cache control
        $mod_date = filemtime($file->path);
        $mod_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;
        if ($mod_date && $mod_since && strtotime($mod_since) >= $mod_date) {
            header('HTTP/1.1 304 Not Modified');
            return true;
        } else {
            header('Last-Modified: ' . gmdate("D, d M Y H:i:s", $mod_date) . ' GMT');
            header('Pragma: public');
        }

        ob_clean();
        flush();
        readfile($file->path);
        return true;
    }
}