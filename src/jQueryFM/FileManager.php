<?php

/**
 * jQuery-based file manager
 */
class jQueryFM_FileManager
{
    /**
     * Provider used to list and manipulate files.
     * This is a read only property
     * @var jQueryFM_FileProvider
     */
    public $provider;

    /**
     * URL to the ajax endpoint
     * @var string
     */
    public $ajax_endpoint;

    /**
     * URL to the static folder that contains file extension icons
     * @var string
     */
    public $icons_url = 'static';

    /**
     * Maximum file size allowed to be uploaded (in bytes)
     * @var int
     */
    public $max_size;

    /**
     * Allow users to upload new files
     * @var boolean
     */
    public $allow_upload = true;

    /**
     * Allow users to delete or rename existing files
     * @var boolean
     */
    public $allow_editing = true;

    /**
     * Allow users to create and explore folders
     * @var boolean
     */
    public $allow_folders = true;

    /**
     * The regular expression for allowed file types, matches against either file mime type or file name.
     * For example, for only allow images, you can use this regular expression: (\.|\/)(gif|jpe?g|png)$
     * @var string
     */
    public $accept_file_types = '';

    /**
     * Maximum size, in bytes, that an image can have for show it as a preview. Defaults to 500 KB, 0 to disable image previewing, -1 means unlimited (all images will be previewed)
     * @warning This option can consume a lot of bandwidth
     * @var int
     */
    public $image_preview_limit = 512000;

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
        'max_file_size' => 'max % each file',
        'accept' => 'Accept',
        'cancel' => 'Cancel',
        'start_upload' => 'Start upload',
        'create_folder' => 'Create folder',
        'create_folder_prompt' => 'Please type the new folder name:',
        'success' => 'Operation completed successfully',
        'number_files' => '% files',
        'home_folder' => 'Home',
        'try_again' => 'Try again',
        'uploading' => 'Uploading...',
        'cancel_upload' => 'Cancel upload',
        'unnamed' => 'Unnamed',
        'error' => 'Error processing the request',
        'error_filetype' => 'File type not allowed',
        'error_maxsize' => 'File is too large',
    );

    public function __construct(jQueryFM_FileProvider $provider)
    {
        $this->provider = $provider;
        $this->provider->manager = $this;
        $max_upload = $this->_php_unit(ini_get('upload_max_filesize'));
        $max_post = $this->_php_unit(ini_get('post_max_size'));
        $memory_limit = $this->_php_unit(ini_get('memory_limit'));
        $this->max_size = min($max_upload, $max_post, $memory_limit);
    }

    /**
     * Render the HTML and JS code necessary to initialize the current instance
     *
     * @param string $id ID of the element where the file manager will be placed
     *
     * @return string
     */
    public function render($id)
    {
        $files = array();

        foreach ($this->provider->read() as $f) {
            $files[] = $this->_export_file($f);
        }
        $settings = array(
            'allow_upload' => $this->allow_upload,
            'allow_editing' => $this->allow_editing,
            'allow_folders' => $this->allow_folders,
            'max_file_size' => $this->max_size,
            'max_file_size_readable' => jQueryFM_FileManagerHelper::format_size($this->max_size),
            'accept_file_types' => $this->accept_file_types,
            'ajax_endpoint' => $this->ajax_endpoint,
            'icons_url' => $this->icons_url,
            'strings' => $this->strings,
            'files' => $files
        );

        //Render template
        ob_start();
        ?>
        <script>
            jQuery('#<?= $id ?>').jquery_fm(<?= json_encode($settings) ?>);
        </script>
        <?php
        return ob_get_clean();
    }

    private function _export_file(FileManagerItem $file)
    {
        $data = array(
            'name' => $file->name,
            'info' => $file->info,
            'is_folder' => $file->is_folder,
        );

        if ($file->icon) {
            $data['icon'] = $file->icon;
        }

        return $data;
    }

    /**
     * Method executed as AJAX and HTTP endpoint
     *
     * @param bool $output_response
     *
     * @return bool|null
     */
    public function process_request($output_response = true)
    {
        $response = array('status' => 'success');

        try {
            $folder = $this->allow_folders && isset($_REQUEST['folder']) ? $_REQUEST['folder'] : '/';

            //Find file
            if (isset($_REQUEST['file'])) {
                $file = false;
                foreach ($this->provider->read($folder, $_REQUEST['file']) as $f) {
                    if ($f->name == $_REQUEST['file']) {
                        $file = $f;
                        break;
                    }
                }
                if (!$file) {
                    throw new FileManagerException('file_not_found');
                }
            }

            //Apply action
            switch ($_REQUEST['action']) {
                case 'upload':
                    if (!$this->allow_upload) {
                        throw new FileManagerException('unauthorized');
                    }
                    if (empty($_FILES)) {
                        throw new FileManagerException('empty_upload');
                    }

                    foreach ($_FILES as $id => $info) {
                        //Check errors
                        foreach ($info['error'] as $index => $error) {
                            if ($error != UPLOAD_ERR_OK) {
                                throw new FileManagerException($error);
                            }
                        }

                        //Move files
                        foreach ($info['error'] as $index => $error) {
                            $created_file = $this->provider->create_file($folder, basename($info['name'][$index]), $info['tmp_name'][$index]);
                            if ($created_file) {
                                $response['file'] = $this->_export_file($created_file);
                            } else {
                                throw new FileManagerException('create_error');
                            }
                        }
                    }
                    break;
                case 'download':
                    if (!$this->provider->download($file)) {
                        throw new FileManagerException('download_error');
                    }

                    break;

                case 'rename':
                    if (!$this->allow_editing) {
                        throw new FileManagerException('unauthorized');
                    }

                    $item = $this->provider->rename($file, isset($_REQUEST['destFolder']) ? $_REQUEST['destFolder'] : $folder, $_REQUEST['destName']);
                    if ($item) {
                        $response['file'] = $this->_export_file($item);
                    } else {
                        throw new FileManagerException('rename_error');
                    }

                    break;

                case 'delete':
                    if (!$this->allow_editing) {
                        throw new FileManagerException('unauthorized');
                    }

                    if (!$this->provider->delete($file)) {
                        throw new FileManagerException('delete_error');
                    }

                    break;

                case 'read':
                    if (!$this->allow_folders) {
                        throw new FileManagerException('unauthorized');
                    }

                    $response['files'] = array();
                    foreach ($this->provider->read($folder) as $file) {
                        $response['files'][] = $this->_export_file($file);
                    }

                    break;

                case 'create_folder':
                    if (!$this->allow_folders || !$this->allow_editing) {
                        throw new FileManagerException('unauthorized');
                    }

                    $item = $this->provider->create_folder($folder, $_REQUEST['name']);
                    if ($item) {
                        $response['file'] = $this->_export_file($item);
                    } else {
                        throw new FileManagerException('create_folder_error');
                    }

                    break;

                default:
                    throw new FileManagerException('invalid_action');
                    break;
            }
        } catch (FileManagerException $err) {
            $response['status'] = 'error';
            $response['message'] = $err->getMessage();
        } catch (Exception $er) {
            $response['status'] = 'error';
            $response['message'] = 'unknown_error';
        }


        if ($output_response) {
            if ($response['status'] == 'error') {
                header("HTTP/1.1 500");
            }

            header('Content-type: application/json');
            echo json_encode($response);
        } else {
            return $response['status'] == 'success';
        }
    }

    /* Helpers */

    private function _php_unit($val)
    {
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

}

class FileManagerItem
{

    /**
     * File name
     * @var string
     */
    public $name;

    /**
     * Real file path
     * @var mixed
     */
    public $path;

    /**
     * Relative folder to this file
     * @var string
     */
    public $folder;

    /**
     * File size (if applicable)
     * @var int
     */
    public $size;

    /**
     * File info to show (file size, number of files, etc.)
     * It can be an HTML string
     * @var string
     */
    public $info;
    /**
     * The current file is a folder
     * @var boolean
     */
    public $is_folder = false;

    /**
     * Base64 version of the icon used to represent this file
     * @var string
     */
    public $icon;
}

class FileManagerException extends Exception
{

}