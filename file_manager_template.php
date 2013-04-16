<?php
/* @var $files FileManagerItem[] */
?>
<div class="jquery_fm">
    <?php
    if (isset($process_request_success)):
        if ($process_request_success):
            ?>
            <p><span class="label label-success"><?= $this->strings['success'] ?></span></p>
            <?php
        else:
            ?>
            <p><span class="label label-important"><?= $this->strings['error'] ?></span></p>
        <?php
        endif;
    endif;
    ?>
    <noscript>
    <style>
        .drag-drop-message{
            display: none;
        }

        .upload span,.upload i{
            display: none;
        }

        .upload input {
            position: static;
            opacity: 1;
            filter: none;
            font-size: inherit;
            direction: inherit;
        }

        .jquery_fm .submit{
            display: inline;
        }

        .file-tools .rename, .create_folder{
            display: none;
        }
    </style>
    </noscript>
    <div class="drag-drop-panel well">
        <?php if ($this->allow_folders): ?>
            <ul class="breadcrumb text-left">
                <li><a href="#"><?php echo $this->strings['home_folder'] ?></a> <span class="divider">/</span></li>
            </ul>
        <?php endif; ?>
        <div class="files">
            <?php
            $i = 0;
            foreach ($files as $file) {
                echo $this->_render_file_item($file);
            }
            ?>
        </div>
        <div class="drag-drop-message">
            <?php echo $this->strings['drop_files'] ?>
            <br/>
            <small class="muted"><?php echo str_replace('%size%', $this->_format_size($this->max_size), $this->strings['max_file_size']) ?></small>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="upload-tools">
        <form action="" method="POST" enctype="multipart/form-data">
            <span class="btn btn-success upload">
                <i class="icon-plus icon-white"></i>
                <span><?php echo $this->strings['add_file'] ?></span>
                <input type="file" name="files[]" multiple="">
            </span>
            <button type="submit" class="btn btn-primary submit">
                <i class="icon-upload icon-white"></i>
                <?php echo $this->strings['start_upload'] ?>
            </button>
            <button class="btn btn-info create_folder">
                <i class="icon-folder-open icon-white"></i>
                <?php echo $this->strings['create_folder'] ?>
            </button>
        </form>
    </div>

    <div class="clearfix"></div>

    <?php
    //Render add file button and elements
    if ($this->allow_upload) :
        ?>
        <div class="upload-queue">
            <div id="sample-upload-row" style="display: none" class="row-fluid fade in upload-row">
                <div class="span2 preview"></div>
                <div class="span3 info"></div>
                <div class="span5">
                    <div class="progress progress-striped active">
                        <div class="bar" style="width: 0%;"></div>
                    </div>
                </div>
                <div class="span2 tools">
                    <a class="btn btn-warning cancel">
                        <i class="icon-ban-circle icon-white"></i>
                        <span><?php echo $this->strings['cancel'] ?></span>
                    </a>
                </div>
            </div>
        </div>
        <?php
    endif;

//Render javascript vars
    ?>
    <script>
        var fm_ajax_endpoint = <?php echo json_encode($this->ajax_endpoint) ?>;
        if (fm_ajax_endpoint.length == 0)
            fm_ajax_endpoint = document.location.href;
        var fm_file_icon = <?php echo json_encode('<img src="' . $this->static_url . '/images/files/%ext%.png" onerror="this.src=\'' . $this->static_url . '/images/files/unknown.png\'" />') ?>;
        var fm_strings = <?php echo json_encode($this->strings) ?>;
        var fm_loader = <?php echo json_encode('<img class="loader" src="' . $this->static_url . '/images/loader.gif" />') ?>;
    </script>
</div>