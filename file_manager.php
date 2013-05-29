<?php

/**
 * jQuery-based file manager
 */
class FileManager {

    /**
     * Directory to manage
     * @var string
     */
    public $path;

    /**
     * URL to the ajax endpoint
     * @var string
     */
    public $ajax_endpoint;

    /**
     * URL to the static folder
     * @var string
     */
    public $static_url = 'static';

    /**
     * Number of columns showed on the file manager (up to 12)
     * @var int
     */
    public $columns = 6;

    /**
     * Maximum file size (in bytes)
     * @var int
     */
    public $max_size;

    /**
     * If active, a thumbnail image will be shown for each image file.
     * @warning This option can consume a lot of bandwidth (you can set limits to this using image_previews_limit)
     * @var boolean 
     */
    public $show_image_previews = TRUE;

    /**
     * Maximum size, in bytes, that an image can be for show as a preview. Defaults to 500 KB, -1 means unlimited (all images will be previewed)
     * @var int 
     */
    public $image_previews_limit = 512000;

    /**
     * Allow users to upload new files
     * @var boolean 
     */
    public $allow_upload = TRUE;

    /**
     * Allow users to delete or rename existings files
     * @var boolean 
     */
    public $allow_editing = TRUE;

    /**
     * Allow users to create and explore folders
     * @var boolean 
     */
    public $allow_folders = TRUE;

    /**
     * Localizable strings
     * @var array
     */
    public $strings = array(
        'add_file' => 'Add file',
        'download' => 'Download',
        'explore' => 'Explore',
        'delete' => 'Delete',
        'rename' => 'Rename',
        'confirm_delete' => 'Are you sure you want to delete this file? The operation can not be undone',
        'prompt_newname' => 'Please type the new file name:',
        'drop_files' => 'Drop your files here!',
        'max_file_size' => 'max %size% each file',
        'accept' => 'Accept',
        'cancel' => 'Cancel',
        'start_upload' => 'Start upload',
        'create_folder' => 'Create folder',
        'create_folder_prompt' => 'Please type the new folder name:',
        'success' => 'Operation completed successfully',
        'error' => 'Error processing the request',
        'number_files' => '%count% files',
        'home_folder' => 'Home',
        'try_again' => 'Try again',
    );

    public function __construct() {
        $max_upload = $this->_return_bytes(ini_get('upload_max_filesize'));
        $max_post = $this->_return_bytes(ini_get('post_max_size'));
        $memory_limit = $this->_return_bytes(ini_get('memory_limit'));
        $this->max_size = min($max_upload, $max_post, $memory_limit);
    }

    private function _return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Retrieves the list of files to manage. This function can be overrided
     * @return FileManagerItem[]
     */
    protected function _files($folder = '/') {
        if ($this->allow_folders) {
            $folder = realpath($this->path . DIRECTORY_SEPARATOR . str_replace('..', '', $folder));
        } else {
            $folder = realpath($this->path);
        }

        $files = array();
        if (is_dir($folder)) {
            if (($handle = opendir($folder)) !== FALSE) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {

                        $file = $this->_populate_file_item($folder . DIRECTORY_SEPARATOR . $entry);
                        if ($file)
                            $files[] = $file;
                    }
                }
                closedir($handle);
            } else {
                throw new RuntimeException("Path '$this->path' is not readable");
            }
        }

        return $files;
    }

    /**
     * Create a FileManagerItem given a path. This function can be overrided to customize the displayed information
     * @return FileManagerItem
     */
    protected function _populate_file_item($path) {
        $item = new FileManagerItem();
        $item->path = $path;
        $item->name = basename($path);
        $item->is_folder = is_dir($path);
        if ($item->is_folder) {
            if (!$this->allow_folders)
                return FALSE;
            $item->info = str_replace('%count%', max(0, iterator_count(new DirectoryIterator($path)) - 2), $this->strings['number_files']);
        } else {
            $item->size = filesize($path);
            $item->info = $this->_format_size($item->size);
        }
        return $item;
    }

    /**
     * Send the selected file to the user. This function can be overrided
     * @return boolean
     */
    protected function _download(FileManagerItem $file) {
        if (!file_exists($file->path))
            return FALSE;

        header('Content-Description: File Transfer');
        header("Content-Type: application/octet-stream");
        header('Content-Disposition: attachment; filename=' . $file->name);
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $file->size);

        //Cache control
        $mod_date = filemtime($file->path);
        $mod_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : FALSE;
        if ($mod_date && $mod_since && strtotime($mod_since) >= $mod_date) {
            header('HTTP/1.1 304 Not Modified');
            return TRUE;
        } else {
            header('Last-Modified: ' . gmdate("D, d M Y H:i:s", $mod_date) . ' GMT');
            header('Pragma: public');
        }

        ob_clean();
        flush();
        readfile($file->path);
        return TRUE;
    }

    /**
     * Save the uploaded file. This function can be overrided
     * @param type $name
     * @param string $path
     * @throws RuntimeException
     * @return FileManagerItem|FALSE
     */
    protected function _upload($folder, $name, $tmp_path) {
        if ($this->allow_folders) {
            $folder_path = $this->path . DIRECTORY_SEPARATOR . $folder;
        } else {
            $folder_path = $this->path;
        }

        if (!is_dir($folder_path)) {
            if (!mkdir($folder_path, 0755))
                throw new RuntimeException("Destination path '$folder_path' cannot be created");
        }

        //Look for empty path
        $file_name = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $i = 0;
        do {
            $path = $folder_path . DIRECTORY_SEPARATOR . $this->_clean_filename($file_name) . ($i == 0 ? '' : " ($i)") . '.' . $extension;
            $i++;
        } while (file_exists($path));

        if (move_uploaded_file($tmp_path, $path)) {
            return $this->_populate_file_item($path);
        }
        return FALSE;
    }

    /**
     * Delete the selected file. This function can be overrided
     * @return boolean
     */
    protected function _delete(FileManagerItem $file) {
        if ($file->is_folder) {
            return is_dir($file->path) && $this->_delete_folder($file->path);
        } else {
            return file_exists($file->path) && unlink($file->path);
        }
    }

    /**
     * Rename the indicated file. This function can be overrided
     * @return boolean
     */
    protected function _rename(FileManagerItem $file, $new_name) {
        //Sanitize destination file
        $dest = dirname($file->path) . DIRECTORY_SEPARATOR . $this->_clean_filename($new_name);

        return file_exists($file->path) && !file_exists($dest) && rename($file->path, $dest);
    }

    /**
     * Create the selected folder. This function can be overrided
     * @return FileManagerItem|FALSE
     */
    protected function _create_folder($folder, $name) {
        //Sanitize destination file
        $dest = $this->path . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $this->_clean_filename($name);

        if (!is_dir($dest) && mkdir($dest, 0755, TRUE) && ($real_path = realpath($dest))) {
            return $this->_populate_file_item($real_path);
        }
        return FALSE;
    }

    public function render() {
        if (!empty($_REQUEST) || !empty($_FILES)) {
            $process_request_success = $this->process_request(FALSE);
        }

        //List files
        $files = $this->_files();

        //Render template
        ob_start();
        include 'file_manager_template.php';

        return ob_get_clean();
    }

    private function _render_file_item(FileManagerItem $file) {

        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));

        $default_img = $this->static_url . '/images/files/unknown.png';

        if ($file->is_folder)
            $img_src = $this->static_url . "/images/files/folder.png";
        else if ($this->show_image_previews && in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'bmp')) && ($this->image_previews_limit < 0 || $file->size < $this->image_previews_limit))
            $img_src = $this->ajax_endpoint . (strpos($this->ajax_endpoint, '?') !== FALSE ? '&' : '?') . 'action=download&file=' . $file->name;
        else
            $img_src = $this->static_url . "/images/files/{$extension}.png";

        ob_start();
        ?>
        <div class="file" data-file="<?php echo $file->name ?>">
            <div class="image-holder">             
                <?php if ($file->is_folder): ?>
                    <button class="open_folder" title="<?php echo $this->strings['explore'] ?>"><img src="<?php echo $img_src ?>" onerror="this.src='<?php echo $default_img ?>'" /></button>
                <?php else: ?>
                    <form action="<?php echo $this->ajax_endpoint ?>" method="POST">
                        <input type="hidden" name="action" value="download" />
                        <input type="hidden" name="file" value="<?php echo $file->name ?>" />
                        <button type="submit" title="<?php echo $this->strings['download'] ?>"><img src="<?php echo $img_src ?>" onerror="this.src='<?php echo $default_img ?>'" /></button>
                    </form> 
                <?php endif; ?>                   
            </div>
            <h4 title="<?php echo htmlspecialchars($file->name) ?>  ">
                <?php echo $file->name ?>    
            </h4>
            <h5><small><?php echo $file->info ?></small></h5>
            <?php if (!empty($file->extra)): ?>
                <h5><small><?php echo $file->extra ?></small></h5>
            <?php endif; ?>

            <?php
            if ($this->allow_editing) {
                ?>    
                <div class="file-tools">                        
                    <form method="POST">
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="file" value="<?php echo $file->name ?>" />
                        <button type="submit" class="btn btn-danger btn-mini delete" title="<?php echo $this->strings['delete'] ?>"><i class="icon-trash icon-white"></i></button>
                    </form>
                    <a class="btn btn-info btn-mini rename" title="<?php echo $this->strings['rename'] ?>"><i class="icon-edit icon-white"></i></a>         
                </div>
                <?php
            }
            ?>
        </div>        
        <?php
        return ob_get_clean();
    }

    public function process_request($output_response = TRUE) {
        $status = 200;
        $response = array();


        $folder = $this->allow_folders && isset($_REQUEST['folder']) ? str_replace('..', '', $_REQUEST['folder']) : '/';

        if (!empty($_FILES) && $this->allow_upload) {
            //Upload files
            foreach ($_FILES as $id => $info) {
                //Check errors
                foreach ($info['error'] as $index => $error) {
                    if ($error != UPLOAD_ERR_OK) {
                        $status = 500;
                    }
                }

                //Move files
                if ($status == 200) {
                    foreach ($info['error'] as $index => $error) {
                        $created_file = $this->_upload($folder, basename($info['name'][$index]), $info['tmp_name'][$index]);
                        if ($created_file) {
                            $response['file_html'] = $this->_render_file_item($created_file);
                        } else {
                            $status = 500;
                        }
                    }
                }
            }
        } else if (isset($_REQUEST['action'])) {
            //Find file 
            if (isset($_REQUEST['file'])) {
                $file = FALSE;
                foreach ($this->_files($folder) as $f) {
                    if ($f->name == $_REQUEST['file']) {
                        $file = $f;
                        break;
                    }
                }
                if (!$file) {
                    $status = 500;
                }
            }

            switch ($_REQUEST['action']) {
                case 'download':
                    if (!$this->_download($file))
                        $status = 500;

                    break;

                case 'rename':
                    if (!$this->allow_editing)
                        break;

                    if (!$this->_rename($file, $_REQUEST['dest']))
                        $status = 500;

                    break;

                case 'delete':
                    if (!$this->allow_editing)
                        break;

                    if (!$this->_delete($file))
                        $status = 500;

                    break;

                case 'list_files':
                    if (!$this->allow_folders)
                        break;

                    $html = array();
                    foreach ($this->_files($folder) as $file) {
                        $html[] = $this->_render_file_item($file);
                    }
                    $response['files'] = implode('', $html);

                    break;

                case 'create_folder':
                    if (!$this->allow_folders || !$this->allow_editing)
                        break;

                    $item = $this->_create_folder($folder, $_REQUEST['name']);
                    if ($item) {
                        $response['file_html'] = $this->_render_file_item($item);
                    } else {
                        $status = 500;
                    }

                    break;
            }
        } else {
            //Nothing to do
            if (!$output_response) {
                return NULL;
            }
        }

        if ($output_response) {
            if ($status != 200) {
                header("HTTP/1.1 $status", TRUE, $status);
                $response['status'] = 'error';
            } else {
                $response['status'] = 'success';
            }

            header('Content-type: application/json');
            echo json_encode($response);
        } else {
            return $status == 200;
        }
    }

    private function _clean_filename($name) {
        return preg_replace("/[^a-zA-Z0-9\-\_\ \.]+/", '', $name);
    }

    private function _format_number($number, $decimals = 0) {
        $locale = localeconv();

        $dec_point = $locale['decimal_point'];
        $thousands_sep = $locale['thousands_sep'];


        return rtrim(number_format($number, $decimals, $dec_point, $thousands_sep), "0$dec_point");
    }

    private function _format_size($size, $kilobyte = 1024, $format = '%size% %unit%') {
        if ($size < $kilobyte) {
            $unit = 'bytes';
        } else {
            $size = $size / $kilobyte; // Convertir bytes a kilobyes
            $units = array('KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
            foreach ($units as $unit) {
                if ($size > $kilobyte) {
                    $size = $size / $kilobyte;
                } else {
                    break;
                }
            }
        }

        return strtr($format, array(
            '%size%' => $this->_format_number($size, 2),
            '%unit%' => $unit
        ));
    }

    private function _delete_folder($directory, $delete_dirs = TRUE) {
        if (!is_dir($directory))
            return FALSE;

        $dirh = opendir($directory);
        if ($dirh == FALSE)
            return FALSE;

        while (($file = readdir($dirh)) !== FALSE) {
            if ($file[0] != '.') {
                $path = $directory . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    if ($delete_dirs)
                        $this->_delete_folder($path, $delete_dirs);
                } else {
                    unlink($path);
                }
            }
        }
        closedir($dirh);
        return rmdir($directory);
    }

}

class FileManagerItem {

    /**
     * File name (unique)
     * @var string
     */
    public $name;

    /**
     * File related data (path, metadata, etc)
     * @var mixed
     */
    public $path;

    /**
     * File size (if applicable)
     * @var int
     */
    public $size;

    /**
     * File info to show (file size, number of files)
     * @var string
     */
    public $info;

    /**
     * Extra data to show (date, owner, etc)
     * @var string
     */
    public $extra;

    /**
     * The current file is a folder
     * @var boolean
     */
    public $is_folder = FALSE;

}