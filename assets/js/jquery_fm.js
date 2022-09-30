/**
 * jquery_fm
 * Advanced file manager and upload widget
 *
 * @author Javier Marín <contacto@ideatic.net>
 *
 * Based on jQuery plugin design pattern by
 * https://gist.github.com/GrantDG/5703502
 */
(function ($) {
    "use strict";
    var PLUGIN_NS = 'jquery_fm';

    var Plugin = function (target, options) {
        this.$T = $(target);

        /** #### OPTIONS #### */
        this._options = $.extend(
            true, // deep extend
            {
                autoHideBreadcrumb: false
            },
            options
        );

        /** #### PROPERTIES #### */
        this._$target = $(target);
        this._currentFolder = '/';
        this._enableDragDrop = window.FormData && 'draggable' in document.createElement('span');

        /** #### INIT #### */
        this._init(target, options);

        return this;
    };

    /** #### INITIALISER #### */
    Plugin.prototype._init = function (target, settings) {
        var plugin = this;
        var $fm = plugin.$fm = $('<div class="jquery_fm" />').appendTo(plugin._$target.empty());
        var $explorer = plugin.$explorer = $('<div class="explorer" />').appendTo($fm);
        var $files = plugin.$files = $('<div class="files" />').appendTo($explorer);
        plugin.onDestroy = [];

        //Load buttons and messages
        var $tools = $('<div class="upload-tools" />');
        if (settings['allow_upload']) {
            //Add files using input file
            $('<a class="btn btn-success upload" />')
                .text(settings['strings']['add_file'])
                .prepend('<i class="icon-plus"></i>')
                .append($('<input type="file" name="files[]" multiple="">').change(function () {
                    plugin._upload(this.files);

                    //Reset input
                    var inputClone = $(this).clone(true);
                    $('<form></form>').append(inputClone)[0].reset();
                    $(this).replaceWith(inputClone);
                }))
                .appendTo($tools);

            //Add files using drag and drop
            if (plugin._enableDragDrop) {
                // Makes sure the dataTransfer information is sent when we drop items
                if (jQuery.event.props) {
                    jQuery.event.props.push('dataTransfer');
                }

                var $dragReceiver = settings['drag_selector'] ? $(settings['drag_selector']) : $explorer;
                $dragReceiver.bind(plugin._namespaceEvents(['drop', 'dragenter', 'dragover']), function (e) {
                    var dataTransfer = e.originalEvent && e.originalEvent.dataTransfer;

                    if (dataTransfer && $.inArray('Files', dataTransfer.types) !== -1) {
                        e.preventDefault();

                        if (e.type == 'drop') {
                            plugin._upload(dataTransfer.files);
                        } else {
                            dataTransfer.dropEffect = 'copy';
                            $dragReceiver.add($explorer).toggleClass('drag-hover', true);
                        }
                    }
                }).bind(plugin._namespaceEvents(['drop', 'mouseleave', 'dragend', 'dragleave']), function (e) {
                    $dragReceiver.add($explorer).toggleClass('drag-hover', false);
                });

                plugin.onDestroy.push(function () {
                    $dragReceiver.unbind('.' + PLUGIN_NS);
                });

                //Show drag drop message
                $('<div class="drag-drop-message" />')
                    .append('<i class="icon-download"></i>')
                    .append(settings['strings']['drop_files'])
                    .append('<br />')
                    .append($('<small />').text(settings['strings']['max_file_size'].replace('%', settings['max_file_size_readable'])))
                    .appendTo($explorer);
            }

            if (settings['allow_paste_upload']) {
                // Add files using Clipboard paste
                // The paste target element(s), by default the complete document
                $(document).on('paste', function (e) {
                    var items = e.originalEvent && e.originalEvent.clipboardData && e.originalEvent.clipboardData.items,
                        files = [];
                    if (items && items.length) {
                        $.each(items, function (index, item) {
                            var file = item.getAsFile && item.getAsFile();
                            if (file) {
                                files.push(file);
                            }
                        });

                        plugin._upload(files);
                    }
                });
            }
        }

        //Create folder button
        if (settings['allow_folders'] && settings['allow_editing']) {
            $('<a class="btn btn-info folder" />')
                .text(settings['strings']['create_folder'])
                .prepend('<i class="icon-folder-open"></i>')
                .click(function () {
                    plugin._ask(plugin._options['strings']["create_folder"], plugin._options['strings']["create_folder_prompt"], true, function (new_name) {
                        plugin._request(null, 'create_folder', {name: new_name}, function (response) {
                            if (response.file) {
                                plugin._createFile(response.file).hide().appendTo(plugin.$files).show('slow');
                            }
                        });
                    });

                    return false;
                })
                .appendTo($tools);
        }

        if ($tools.children().length > 0) {
            $fm.append($tools);
        }

        //Load files and breadcrumb
        if (typeof settings.files === "undefined") {
            plugin.navigateTo('/');
        } else {
            $(settings.files).each(function () {
                $files.append(plugin._createFile(this));
            });
        }

        plugin._updateBreadcrumb();
    };

    /** #### PUBLIC API #### */


    /**
     * Get the current shown files objets
     * @returns array
     */
    Plugin.prototype.currentFiles = function () {
        var plugin = this;

        var files = [];
        plugin.$files.children('.file').each(function () {
            files.push($(this).data('file'))
        });
        return files;
    };

    /**
     * Get the current shown folder
     * @returns array
     */
    Plugin.prototype.currentFolder = function () {
        return this._currentFolder;
    };

    /**
     * Navigate the explorer to the given folder
     * @param string folder
     * @returns jQuery
     */
    Plugin.prototype.navigateTo = function (folder) {
        var plugin = this;

        if (plugin._options['allow_folders']) {
            folder = folder.replace(/\/+/g, '/');

            //Remove current files and show loader
            var $files = plugin.$explorer.find('.files').fadeOut('normal', function () {
                $(this).html('<div class="loader" />').fadeIn('normal');
            });

            //Request new files
            plugin._request(null, 'read', {folder: folder}, function (response) {

                plugin._currentFolder = folder;

                $files.stop(true, true).fadeTo('normal', 1).empty();
                $(response.files).each(function () {
                    plugin._createFile(this).hide().appendTo(plugin.$files).fadeTo("slow", 1);
                });

                //Update breadcrumb
                plugin._updateBreadcrumb();
            }, function () {
                //Show try again button when folder load fails
                $files.stop(true, true).empty().append($('<a href="#" />').text(plugin._options['strings']['try_again']).click(function () {
                    plugin.navigateTo(folder);
                    return false;
                }));
            });
        }
        return this.$T;        // support jQuery chaining
    };

    /** #### PRIVATE METHODS #### */
    Plugin.prototype._request = function ($file_elm, action, params, onsuccess, onfail, onprogress) {
        var plugin = this;

        var data;
        var ajax = {};
        if (params instanceof FormData) {
            data = params;
            data.append('action', action);

            ajax.processData = false;
            ajax.contentType = false;
        } else {
            data = $.extend({action: action}, params);

            if (typeof data.folder == 'undefined') {
                data.folder = plugin._currentFolder;
            }
        }

        if (onprogress) {
            ajax.xhr = function () {
                //Upload progress
                var xhr = jQuery.ajaxSettings.xhr() || new window.XMLHttpRequest();
                if (xhr.upload) {
                    xhr.upload.addEventListener("progress", function (evt) {
                        if (evt.lengthComputable) {
                            onprogress({
                                loaded: event.loaded,
                                total: event.total,
                                progress: (event.loaded / event.total * 100 | 0)
                            });
                        }
                    }, false);
                }
                return xhr;
            };
        }


        ajax = $.extend(ajax, {
            type: 'POST',
            url: plugin._options['ajax_endpoint'],
            data: data,
            dataType: 'json',
            cache: false,
            success: function (data) {
                //Remove error badge
                if ($file_elm) {
                    $file_elm.find('.error').fadeOut('slow', function () {
                        $(this).remove();
                    });
                }

                //Success callback
                if (onsuccess) {
                    onsuccess(data);
                }
            },
            error: function (jqXHR) {
                var error_id = jqXHR.responseJSON && jqXHR.responseJSON.message;
                var message = plugin._options['strings'][error_id] || plugin._options['strings']['error'];

                if ($file_elm) {
                    plugin._setError($file_elm, message);
                } else {
                    var $message = $('<p />').append($('<span class="label label-danger" />').text(message))
                        .hide()
                        .prependTo(plugin.$fm)
                        .fadeIn();
                    window.setTimeout(function () {
                        $message.fadeOut('slow', function () {
                            $message.remove();
                        });
                    }, 20000);
                }

                //Fail callback
                if (onfail) {
                    onfail(data);
                }
            }
        });

        ajax = $.extend(ajax, plugin._options['ajax_config'] || {});

        return $.ajax(ajax);
    };

    Plugin.prototype._namespaceEvents = function (names) {
        return names.map(function (type) {
            return "" + type + "." + PLUGIN_NS;
        }).join(" ");
    };
    Plugin.prototype._setError = function ($file, message) {
        $('<span class="error" />').text('!')
            .attr('title', message)
            .hide()
            .prependTo($file)
            .fadeOut("fast").fadeIn("fast").fadeOut("fast").fadeIn("fast").fadeOut("fast").fadeIn("fast");//Blink error

        $file.attr('title', message).find('.cancel').remove();
    };

    Plugin.prototype._findByName = function (name) {

        var plugin = this;
        return plugin.$files.children('.file').filter(function () {
            return $(this).data('file').name == name;
        });
    };

    /**
     * Prepare the given element so that the dragged files are moved to the specified destination folder
     * @private
     */
    Plugin.prototype._prepareFileDrag = function ($element, destFolder) {
        var plugin = this;

        if (plugin._enableDragDrop && plugin._options['allow_folders']) {
            $element.bind('drop dragenter dragover', function (e) {
                var dataTransfer = e.originalEvent && e.originalEvent.dataTransfer;

                if (dataTransfer && $.inArray(PLUGIN_NS, dataTransfer.types) !== -1) {
                    e.preventDefault();

                    if (e.type == 'drop') {
                        var srcName = dataTransfer.getData(PLUGIN_NS);
                        var $srcFile = plugin._findByName(srcName);

                        plugin._request($srcFile, 'rename', {
                            file: srcName,
                            destName: srcName,
                            destFolder: destFolder
                        }, function (response) {
                            //Hide moved file
                            $srcFile.hide('slow');

                            //Update destination folder
                            if ($element.is('.file') && response.file) {
                                //Replace old file with the new one
                                $element.find('.info').fadeOut('normal', function () {
                                    var $new = plugin._createFile(response.file);
                                    $new.find('.info').hide().fadeIn('slow');
                                    $element.replaceWith($new);
                                });
                            }

                        });
                    } else {
                        dataTransfer.dropEffect = 'move';
                        $element.toggleClass('drag-hover', true);
                    }
                }
            }).bind('drop mouseleave dragend dragleave', function () {
                $element.toggleClass('drag-hover', false);
            });
        }
    };

    Plugin.prototype._createFile = function (fileData) {
        var plugin = this;
        var $file_elm = $('<div class="file" />')
            .data('file', fileData);

        //Icon
        var src, onclick;
        if (fileData['is_folder']) {
            src = this._options['icons_url'] + "/folder.png";
            onclick = function () {
                plugin.navigateTo(plugin._currentFolder + '/' + fileData['name']);
            };
        } else {
            src = this._options['icons_url'] + "/" + fileData['name'].split('.').pop() + ".png";
            onclick = function () {
                //Download file
                var download = plugin._options['force_downloads'];
                var fileUrl = plugin._options['ajax_endpoint'] + (plugin._options['ajax_endpoint'].indexOf('?') == -1 ? '?' : '&') + 'action=' + (download ? 'download' : 'show') + '&file=' + fileData['name'] + '&folder=' + plugin._currentFolder;

                if (download) {
                    //Create a temporary iframe that is used to request the fileUrl as a GET request
                    $("<iframe>")
                        .hide()
                        .prop("src", fileUrl)
                        .appendTo("body");
                } else {
                    //Show the file in a new window, a set the title with the file name
                    var w = window.open(fileUrl);
                    w.onload = function () {
                        w.document.title = fileData['name'];
                    };
                }

            };
        }
        $('<div class="icon" />')
            .click(onclick)
            .append($('<img />').attr('draggable', false).attr('src', fileData['icon'] || src).bind('error', function (event) {
                $(this).unbind(event).attr('src', plugin._options['icons_url'] + "/unknown.png");
            }))
            .appendTo($file_elm);

        //Name and info
        $('<h4 />').attr('title', fileData['name']).text(fileData['name']).appendTo($file_elm);
        $('<div class="info" />').html(fileData['info']).appendTo($file_elm);

        //Title
        if (fileData['title']) {
            $file_elm.attr('title', fileData['title']);
        }

        //Tools
        var $tools = $('<div class="tools" />');
        if (fileData['uploading']) {
            //Cancel upload
            $('<a class="btn btn-sm btn-warning cancel" />')
                .attr('title', plugin._options['strings']['cancel_upload'])
                .prepend('<i class="icon-cancel"></i>')
                .appendTo($tools);
        } else if (plugin._options['allow_editing'] && (!fileData.hasOwnProperty('allow_edit') || fileData['allow_edit'] == true)) {
            //Delete
            $('<a class="btn btn-sm btn-danger delete" />')
                .attr('title', plugin._options['strings']['delete'])
                .prepend('<i class="icon-trash"></i>')
                .click(function () {
                    plugin._ask(plugin._options['strings']["delete"], plugin._options['strings']["confirm_delete"], false, function () {
                        plugin._request($file_elm, 'delete', {file: fileData.name}, function () {
                            //Remove file from explorer
                            $file_elm.hide('slow', function () {
                                $file_elm.remove();

                                //Trigger event
                                plugin._$target.trigger('delete', fileData);
                            });

                        });
                    });

                    return false;
                })
                .appendTo($tools);

            //Rename
            $('<a class="btn btn-sm btn-info rename" />')
                .attr('title', plugin._options['strings']['rename'])
                .prepend('<i class="icon-edit"></i>')
                .click(function () {
                    plugin._ask(plugin._options['strings']["rename"], plugin._options['strings']["prompt_newname"], fileData.name, function (newName) {
                        if (fileData.name == newName) {
                            return;
                        }

                        plugin._request($file_elm, 'rename', {
                            file: fileData.name,
                            destName: newName
                        }, function (response) {
                            //Replace old file with the new one
                            $file_elm.fadeOut('normal', function () {
                                var $new = plugin._createFile(response.file).hide().fadeTo("slow", 1);
                                $file_elm.replaceWith($new);

                                //Trigger event
                                plugin._$target.trigger('rename', fileData);
                            });
                        });
                    });

                    return false;
                })
                .appendTo($tools);
        }

        if ($tools.children().length > 0) {
            $tools.appendTo($file_elm);
        }

        //Drag & drop
        if (plugin._options['allow_folders']) {
            $file_elm.attr('draggable', true)
                .bind('dragstart', function (e) {
                    if (!fileData['uploading']) {
                        e.dataTransfer.setData("Text", fileData.name);
                        e.dataTransfer.setData(PLUGIN_NS, fileData.name);
                    }
                });


            //Enable drag&drop from other files to this folder
            if (fileData['is_folder']) {
                plugin._prepareFileDrag($file_elm, plugin._currentFolder + '/' + fileData['name']);
            }
        }

        //Trigger event
        plugin._$target.trigger('create', fileData);

        return $file_elm;
    };

    Plugin.prototype._updateBreadcrumb = function () {
        if (!this._options['allow_folders']) {
            return;
        }

        var plugin = this;

        //Find or create breadcrumb
        var $bc = plugin.$explorer.find('.breadcrumb');

        if (!$bc.length) {
            $bc = $('<ol class="breadcrumb" />').prependTo(plugin.$explorer);
        }
        $bc.empty();

        //Fill breadcrumb
        var folders = ("" == plugin._currentFolder ? "/" : plugin._currentFolder).split('/');
        if (folders[0] == "" && folders[1] == "") {
            folders.splice(0, 1);
        }

        if (folders.length == 1 && this._options['autoHideBreadcrumb']) {
            $bc.remove();
        } else {
            for (var i in folders) {
                var folder = i == 0 ? plugin._options['strings']['home_folder'] : folders[i];

                if (folder.length) {
                    if (i == folders.length - 1) {//Last folder
                        $bc.append('<li class="active">' + folder + '</li>');
                    } else {
                        //Navigate on item click
                        var folderPath = folders.slice(0, parseInt(i) + 1).join('/');
                        var $a = $('<a />')
                            .text(folder)
                            .data('folder', folderPath)
                            .click(function () {
                                plugin.navigateTo($(this).data('folder'));
                                return false;
                            });
                        $bc.append($('<li />').append($a));

                        //Allow file drags to the breadcrumb for file moving between folders
                        plugin._prepareFileDrag($a, folderPath);
                    }
                }
            }
        }
    };

    //Validate a file, checking if it's fit to be uploaded
    Plugin.prototype._validate = function (file) {
        var plugin = this, message = '';

        //Check type and name
        var regex = new RegExp(plugin._options['accept_file_types'], 'i');
        if (plugin._options['accept_file_types'] && !(regex.test(file.type) || regex.test(file.name))) {
            message = plugin._options['strings']['error_filetype'];
        } else if (file.size > plugin._options['max_file_size']) {
            message = plugin._options['strings']['error_maxsize'];
        }

        return {
            success: message == '',
            message: message
        };
    };

    Plugin.prototype._upload = function (files, folder) {
        var plugin = this;

        for (var i = 0; i < files.length; i++) {
            (function (file) {
                //Show upload progres
                var $file_elm = plugin._createFile({
                    name: file.name || plugin._options['strings']['unnamed'],
                    info: file.size ? (file.size / 1024 | 0) + ' KB' : '',
                    is_folder: false,
                    uploading: true
                }).hide().appendTo(plugin.$files).show('slow');

                var $icon = $file_elm.addClass('uploading').attr('title', plugin._options['strings']['uploading']).find('.icon');

                //Preview icon
                if (typeof FileReader != 'undefined' && file.type.match('image')) {
                    var reader = new FileReader();
                    reader.onload = function (event) {
                        var image = new Image();
                        image.src = event.target.result;

                        $icon.find('img').replaceWith(image);
                    };

                    reader.readAsDataURL(file);
                }

                //Validate file
                var validation = plugin._validate(file);
                if (validation.success) {
                    //Post XHR request
                    var finished = false;
                    var data = new FormData();
                    data.append('folder', folder || plugin._currentFolder);
                    data.append('files[]', file);
                    var xhr = plugin._request($file_elm, 'upload', data, function (response) {
                        //Success
                        finished = true;
                        $icon.loader(100).loader(false, function () {
                            $file_elm.removeClass('uploading');
                            if (response.file) {
                                $file_elm.replaceWith(plugin._createFile(response.file));
                            }

                            //Trigger event
                            plugin._$target.trigger('upload', response.file);
                        });

                    }, function () {
                        //Error
                        finished = true;
                        $icon.loader(false);
                    }, function (status) {
                        //Progress
                        if (status.progress == 100) {
                            //Animate server-side processing
                            $({val: 85}).animate({val: 100}, {
                                duration: 3000,
                                step: function () {
                                    if (!finished) {
                                        $icon.loader(this.val);
                                    }
                                }
                            });
                        } else {
                            $icon.loader(status.progress * 0.85);//Keep the 15% for server side processing
                        }
                    });

                    //Cancel
                    $file_elm.find('.cancel').click(function () {
                        xhr.abort();
                        $file_elm.hide('slow');
                        return false;
                    });
                } else {
                    //Show error
                    plugin._setError($file_elm, validation.message);
                }

            })(files[i]);
        }
    };

    Plugin.prototype._ask = function (title, text, promptValue, onOK, onCancel) {
        var plugin = this;

        if (jQuery().modal) {
            //Create markup
            var $response = promptValue !== false ? $('<input />', {
                type: 'text',
                id: 'response',
                'class': 'form-control',
                value: promptValue === true ? '' : promptValue,
                style: 'width:85%',
                autofocus: true
            }) : $();
            var $modal = $('<div class="jquery_fm modal fade"> \
<div class="modal-dialog"> \
  <div class="modal-content"> \
  <div class="modal-header"> \
   <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button> \
   <h4 class="modal-title"></h4> \
  </div> \
<div class="modal-body"></div> \
  <div class="modal-footer"> \
 <button type="button" class="btn btn-default" data-dismiss="modal">Close</button> \
 <button type="button" class="btn btn-primary" data-dismiss="modal">Save changes</button> \
</div> \
</div></div></div>').first();//Ignore comment
            $modal.find('.modal-title').text(title);
            $modal.find('.modal-body').text(text).append('<br/><br/>').append($response);

            var $acceptButton = $modal.find('.btn-primary').text(plugin._options['strings']['accept']).click(function () {
                onOK($response.val());
            });

            $modal.find('.btn-default').text(plugin._options['strings']['cancel']).click(function () {
                if (onCancel) {
                    onCancel();
                }
            });

            //Submit on Enter press
            $response.keypress(function (event) {
                if (event.which == 13) {
                    event.preventDefault();
                    $acceptButton.click();
                }
            });

            //Show modal and free memory on close
            $modal.modal('show').on('hidden', function () {
                $modal.remove();
            });

            //Focus main element
            if ($response.length > 0) {
                $response.focus();
            } else {
                $acceptButton.focus();
            }
        } else {
            //Use standard prompt and confirm functions
            var resp = promptValue ? prompt(text, promptValue === true ? '' : promptValue) : confirm(text);

            if (resp) {
                onOK(resp);
            } else if (onCancel) {
                onCancel();
            }
        }
    };


    /**
     * Destroy the current jQueryFM instance
     * @public
     */
    Plugin.prototype.destroy = function () {
        $(this.onDestroy).each(function (i, c) {
            c();
        });
        $(this.$T).removeData(PLUGIN_NS);
    };


    /*###################################################################################
     * JQUERY HOOK
     ###################################################################################*/

    /**
     * Generic jQuery plugin instantiation method call logic
     *
     * Method options are stored via jQuery's data() method in the relevant element(s)
     * Notice, myActionMethod mustn't start with an underscore (_) as this is used to
     * indicate private methods on the PLUGIN class.
     */
    $.fn[PLUGIN_NS] = function (methodOrOptions) {
        if (!$(this).length) {
            return $(this);
        }
        var instance = $(this).data(PLUGIN_NS);

        // CASE: action method (public method on PLUGIN class)
        if (instance
            && methodOrOptions.indexOf('_') != 0
            && instance[methodOrOptions]
            && typeof (instance[methodOrOptions]) == 'function') {

            return instance[methodOrOptions].apply(instance, Array.prototype.slice.call(arguments, 1));
            // CASE: argument is options object or empty = initialise
        } else if (typeof methodOrOptions === 'object' || !methodOrOptions) {

            instance = new Plugin($(this), methodOrOptions);    // ok to overwrite if this is a re-init
            $(this).data(PLUGIN_NS, instance);
            return $(this);

            // CASE: method called before init
        } else if (!instance) {
            $.error('Plugin must be initialised before using method: ' + methodOrOptions);

            // CASE: invalid method
        } else if (methodOrOptions.indexOf('_') == 0) {
            $.error('Method ' + methodOrOptions + ' is private!');
        } else {
            $.error('Method ' + methodOrOptions + ' does not exist.');
        }
    };


})(jQuery);


/**
 * jQuery Pie Loader Plugin
 */
(function ($) {

    $.fn.loader = function (progress, onfinish) {

        var add_prefixes = function (styles, properties) {
            for (var name in properties) {
                var value = properties[name];

                $(['-moz', '-ms', '-o', '-webkit']).each(function () {
                    styles[this + '-' + name] = value;
                });
            }
            return styles;
        };

        var center = function ($elm) {
            $elm.css({
                position: 'absolute',
                left: ($elm.parent().width() / 2 - $elm.width() / 2) + 'px',
                top: ($elm.parent().height() / 2 - $elm.height() / 2) + 'px'
            });
        }
        return $(this).each(function () {
            var $this = $(this);


            //Create required markup
            var $pie = $this.data('loader-pie');
            var intervalId;
            var firstRun = false;
            if (!$pie || $pie.parent().length == 0) {
                firstRun = true;
                $pie = $('<div><div><div class="loader-pie"></div></div><div class="loader-pie"></div></div>').hide().prependTo($this);
                $this.data('loader-pie', $pie);

                //Detect element resize
                var lastSize = {
                    w: $this.width(),
                    h: $this.height()
                };
                intervalId = setInterval(function () {
                    if ($this.width() !== lastSize.w || $this.height() !== lastSize.h) {
                        lastSize.w = $this.width();
                        lastSize.h = $this.height();
                        $this.loader($this.data('loader-progress'));
                    }
                }, 200);
            }
            $this.data('loader-progress', progress);


            var animate_pie_size = function (destSize, complete) {
                $pie.animate(
                    {
                        fontSize: destSize
                    },
                    {
                        duration: 400,
                        step: function (fs) {
                            center($pie);
                        },
                        complete: function () {
                            if (complete) {
                                complete();
                            }
                        }
                    });
            }

            //Apply progress
            if (progress === false) {
                //Remove pie with animation
                animate_pie_size(0, function () {
                    $pie.remove();
                    $this.removeData('loader-pie loader-progress');
                    clearInterval(intervalId);
                    if (onfinish) {
                        onfinish();
                    }
                });
            } else {
                progress = Math.min(progress, 100);

                //Apply styles
                $this.css('position', 'relative');

                //Pie
                var size = Math.min($this.width(), $this.height()) / 1.618;
                $pie.css({
                    fontSize: size + 'px',
                    width: '1em',
                    height: '1em',
                    zIndex: 99999
                });


                //Rotating slide
                $pie.children().eq(0).css({
                    overflow: 'hidden',
                    width: '.5em',
                    height: '1em',
                    position: 'absolute',
                    left: progress > 50 ? 0 : '.5em'
                }).children().first().css(add_prefixes({
                    width: '100%',
                    height: '100%',
                    borderRadius: '.5em 0 0 .5em',
                    position: 'absolute',
                    left: progress > 50 ? 0 : '-.5em'
                }, {
                    'transform-origin': 'right center',
                    'transform': 'rotate(' + (360 / 100 * progress) + 'deg)'
                }));

                //Fixed slice
                $pie.children().eq(1).css({
                    display: progress > 50 ? 'block' : 'none',
                    width: '.5em',
                    height: '1em',
                    borderRadius: '0 .5em .5em 0',
                    position: 'absolute',
                    left: '.5em'
                });

                if (firstRun) {
                    //Animate pie creation
                    $pie.show().css('fontSize', 0);
                    animate_pie_size(size);
                } else {
                    center($pie);
                }
            }
        });
    };

}(jQuery));
