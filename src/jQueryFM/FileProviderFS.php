<?php

/**
 * Implements a file provider that uses the local file system
 */
class jQueryFM_FileProviderFS extends jQueryFM_FileProvider
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

    protected function _get_folder_path($folder)
    {
        if ($this->manager->allow_folders) {
            $real_folder = realpath($this->path . DIRECTORY_SEPARATOR . str_replace('..', '', $folder));
        } else {
            $real_folder = realpath($this->path);
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
            $item->info = jQueryFM_FileManagerHelper::format_size($item->size);

            //Extract preview
            if ($this->manager->image_preview_limit < 0 || $item->size < $this->manager->image_preview_limit) {
                $extension = strtolower(pathinfo($item->name, PATHINFO_EXTENSION));
                if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'))) {
                    //Inline icon (all images in one request, but disable cache)
                    //$item->icon = "data:image/$extension;base64," . base64_encode(file_get_contents($item->path));

                    $item->icon = $this->manager->ajax_endpoint . (strpos($this->manager->ajax_endpoint, '?') !== false ? '&' : '?') . http_build_query(
                            array(
                                'action' => 'download',
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

    public function create_file($folder, $name, $tmp_path)
    {
        $folder_path = $this->_get_folder_path($folder);

        if (!is_dir($folder_path)) {
            if (!mkdir($folder_path, 0755)) {
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

    public function rename(FileManagerItem $file, $new_folder, $new_name)
    {
        //Sanitize destination file
        $path = $this->_get_folder_path($new_folder);
        $dest = $path . DIRECTORY_SEPARATOR . $this->_clean_filename($new_name);

        if (file_exists($file->path) && !file_exists($dest) && rename($file->path, $dest)) {
            return $this->_populate_file_item($dest, $file->folder);
        }
        return false;
    }

    public function delete(FileManagerItem $file)
    {

        if ($file->is_folder) {
            return is_dir($file->path) && jQueryFM_FileManagerHelper::recursive_delete($file->path);
        } else {
            return file_exists($file->path) && unlink($file->path);
        }
    }

    public function download(FileManagerItem $file, $force = true)
    {
        if (!file_exists($file->path)) {
            return false;
        }

        header("Content-Type: application/octet-stream");
        if ($force) {
            header('Content-Disposition: attachment; filename=' . $file->name);
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