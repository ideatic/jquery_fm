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
     * Localizable strings
     * @var array
     */
    public $strings = array(
        'add_file' => 'Add file',
        'download' => 'Download',
        'delete' => 'Delete',
        'rename' => 'Rename',
        'confirm_delete' => 'Are you sure you want to delete this file? The operation can not be undone',
        'prompt_newname' => 'Please type the new file name.',
        'drop_files' => 'Drop your files here!',
        'max_file_size' => 'max %size% each file',
        'accept' => 'Accept',
        'cancel' => 'Cancel',
        'start_upload' => 'Start upload',
        'ajax_error' => 'Error doing a %operation% operation on file %file%',
        'success' => 'Operation completed successfully',
        'error' => 'Error processing the request',
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
    protected function _files() {
        $files = array();
        if (is_dir($this->path)) {
            if (($handle = opendir($this->path)) !== FALSE) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        $item = new FileManagerItem();
                        $item->tag = $this->path . DIRECTORY_SEPARATOR . $entry;
                        $item->name = basename($item->tag);
                        $item->size = filesize($item->tag);
                        $files[] = $item;
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
     * Send the selected file to the user. This function can be overrided
     * @return boolean
     */
    protected function _download(FileManagerItem $file) {
        if (!file_exists($file->tag))
            return FALSE;

        header('Content-Description: File Transfer');
        header("Content-Type: application/octet-stream");
        header('Content-Disposition: attachment; filename=' . $file->name);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $file->size);
        ob_clean();
        flush();
        readfile($file->tag);
    }

    /**
     * Save the uploaded file. This function can be overrided
     * @param type $name
     * @param string $path
     * @throws RuntimeException
     * @return FileManagerItem|FALSE
     */
    protected function _upload($name, $tmp_path) {
        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0755))
                throw new RuntimeException("Destination path '$path' cannot be created");
        }

        //Look for empty path
        $file_name = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $i = 0;
        do {
            $path = $this->path . DIRECTORY_SEPARATOR . $this->_clean_filename($file_name) . ($i == 0 ? '' : " ($i)") . '.' . $extension;
            $i++;
        } while (file_exists($path));

        if (move_uploaded_file($tmp_path, $path)) {
            $item = new FileManagerItem();
            $item->tag = $path;
            $item->name = basename($item->tag);
            $item->size = filesize($item->tag);
            return $item;
        }
        return FALSE;
    }

    /**
     * Delete the selected file. This function can be overrided
     * @return boolean
     */
    protected function _delete(FileManagerItem $file) {

        return file_exists($file->tag) && unlink($file->tag);
    }

    /**
     * Rename the indicated file. This function can be overrided
     * @return boolean
     */
    protected function _rename(FileManagerItem $file, $new_name) {
        //Sanitize destination file
        $dest = dirname($file->tag) . DIRECTORY_SEPARATOR . $this->_clean_filename($new_name);

        return file_exists($file->tag) && !file_exists($dest) && rename($file->tag, $dest);
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

        if ($this->show_image_previews && in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'bmp')) && ($this->image_previews_limit < 0 || $file->size < $this->image_previews_limit))
            $img_src = $this->ajax_endpoint . (strpos($this->ajax_endpoint, '?') !== FALSE ? '&' : '?') . 'action=download&file=' . $file->name;
        else
            $img_src = $this->static_url . "/images/files/{$extension}.png";

        ob_start();
        ?>
        <div class="file" data-file="<?php echo $file->name ?>">
            <div class="image-holder">
                <img src="<?php echo $img_src ?>" onerror="this.src='<?php echo $default_img ?>'" />
            </div>
            <h4><?php echo $file->name ?></h4>
            <h5><small><?php echo $this->_format_size($file->size) ?></small></h5>
            <?php if (!empty($file->extra)): ?>
                <h5><small><?php echo $file->extra ?></small></h5>
            <?php endif; ?>
            <div class="file-tools">                    
                <form action="<?php echo $this->ajax_endpoint ?>" method="POST">
                    <input type="hidden" name="action" value="download" />
                    <input type="hidden" name="file" value="<?php echo $file->name ?>" />
                    <button type="submit" class="btn btn-primary"><i class="icon-download-alt icon-white"></i> <?php echo $this->strings['download'] ?></button>
                </form>
                <?php
                if ($this->allow_editing) {
                    ?>                          
                    <form method="POST">
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="file" value="<?php echo $file->name ?>" />
                        <button type="submit" class="btn btn-danger btn-small delete"><i class="icon-trash icon-white"></i> <?php echo $this->strings['delete'] ?></button>
                    </form>
                    <div>
                        <a class="btn btn-small rename"><i class="icon-edit"></i> <?php echo $this->strings['rename'] ?></a>         
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>        
        <?php
        return ob_get_clean();
    }

    public function process_request($output_response = TRUE) {
        $status = 200;
        $response = array();

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
                        $created_file = $this->_upload(basename($info['name'][$index]), $info['tmp_name'][$index]);
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
            $file = FALSE;
            foreach ($this->_files() as $f) {
                if ($f->name == $_REQUEST['file']) {
                    $file = $f;
                    break;
                }
            }

            if (!$file) {
                $status = 500;
            } else {
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
                }
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

}

class FileManagerItem {

    /**
     * File name (unique)
     * @var string
     */
    public $name;

    /**
     * File size (in bytes)
     * @var int
     */
    public $size;

    /**
     * File related data (path, metadata, etc)
     * @var mixed
     */
    public $tag;

    /**
     * Extra data to show (date, owner, etc)
     * @var string
     */
    public $extra;

}