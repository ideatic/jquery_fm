$(function() {

    var initialize = function(element) {
        var manager = $(element);
        var current_folder = '/';


        var ajax_request = function(data, success) {
            if (typeof data.folder == 'undefined')
                data.folder = current_folder;

            return $.post(fm_ajax_endpoint, data, function(data) {
                if (success)
                    success(data);
            }).fail(function() {
                $(manager).prepend('<p><span class="label label-important">' + fm_strings["error"] + '</span></p>');
            });
        };

        var build_breadcrumb = function() {
            var folders = current_folder.split('/');
            var breadcrumb = manager.find('.breadcrumb').empty();
            for (var i in folders) {
                var folder = i == 0 ? fm_strings['home_folder'] : folders[i];

                if (folder.length == 0)
                    continue;

                if (i == folders.length - 1) {
                    breadcrumb.append('<li class="active">' + folder + '</li>');
                } else {
                    var li = $('<li><a href="#">' + folder + '</a> <span class="divider">/</span></li>');
                    $(li).find('a').click(function() {
                        open_folder(folders.slice(0, i).join('/'));
                        return false;
                    });
                    breadcrumb.append(li);
                }
            }

        };
        build_breadcrumb();

        var open_folder = function(path) {
            path = path.replace(/\/+/g, '/');

            console.log("List " + path);
            //Show loader
            manager.find('.files').fadeOut('normal', function() {
                $(this).html(fm_loader).fadeIn();
            });

            //Request
            ajax_request({
                action: 'list_files',
                folder: path
            }, function(data) {
                current_folder = path;

                $('.files').stop().empty();
                if (data.files) {
                    add_file(data.files);
                }

                //Update breadcrumb
                build_breadcrumb();
            }).fail(function() {
                var retry = $('<a href="#">' + fm_strings['try_again'] + '</a>').click(function() {
                    open_folder(path);
                });
                $('.files').html(retry);
            });
        };



        var initialize_file_tools = function(element) {
            var dom = $(element || manager);

            //Delete button
            dom.find('.btn.delete').click(function() {
                var file = $(this).closest('.file');

                show_modal(fm_strings["delete"], fm_strings["confirm_delete"], false, function() {
                    ajax_request({
                        action: 'delete',
                        file: $(file).data('file')
                    }, function() {
                        $(file).hide('slow', function() {
                            $(this).remove();
                        });
                    });
                });

                return false;
            });

            //Rename button
            dom.find('.btn.rename').click(function() {
                var file = $(this).closest('.file');

                show_modal(fm_strings["rename"], fm_strings["prompt_newname"], $(file).data('file'), function(new_name) {
                    ajax_request({
                        action: 'rename',
                        file: $(file).data('file'),
                        dest: new_name
                    }, function() {
                        $(file).data('file', new_name);
                        $(file).find('h4').fadeOut('slow', function() {
                            $(this).text(new_name);
                        }).fadeIn('slow');
                    });
                });

                return false;
            });

            //Open folder
            dom.find('.open_folder').click(function() {
                open_folder(current_folder + '/' + $(this).closest('.file').data('file'));
                return false;
            });
        };
        initialize_file_tools();

        //Create folder button
        manager.find('.create_folder').click(function() {
            show_modal(fm_strings["create_folder"], fm_strings["create_folder_prompt"], '', function(new_name) {
                ajax_request({
                    action: 'create_folder',
                    name: new_name
                }, function(data) {
                    if (data.file_html) {
                        add_file(data.file_html, 'show');
                    }
                });
            });

            return false;
        });


        if (window.FormData) {
            //Enable drag-drop (if supported)
            if ('draggable' in document.createElement('span')) {
                // Makes sure the dataTransfer information is sent when we
                // Drop the item in the drop box.
                jQuery.event.props.push('dataTransfer');

                manager.find('.drag-drop-panel').bind('drop', function(e) {
                    e.preventDefault();
                    upload_files(e.dataTransfer.files);
                }).bind('dragenter dragover', function() {
                    $(this).toggleClass('drag-hover', true);
                    return false;
                }).bind('drop mouseleave dragend', function() {
                    $(this).toggleClass('drag-hover', false);
                    return false;
                });

                $('.drag-drop-message').show();
            } else {
                //Drag-drop not supported
                $('.drag-drop-message').hide();
            }

            //Enable input file upload
            manager.find('input[type=file]').bind('change', function(e) {
                upload_files(this.files);
            });
        } else {
            //Show old-style submit button
            manager.find('.submit').show();
        }

        var add_file = function(html, effect) {
            //var new_file = $(html.trim()).hide().appendTo('.files').show();
            var new_file = $(html.trim()).each(function() {
                if (this.nodeType == 3)
                {
                    $(this).appendTo('.files');
                }
                else
                {
                    $(this).hide().appendTo('.files');
                    if (effect == 'show')
                        $(this).show('slow');
                    else
                        $(this).fadeIn('slow');
                }
            });
            initialize_file_tools(new_file);
        }

        var show_modal = function(title, text, prompt_value, ok, cancel) {
            if (jQuery().modal) {
                var prompt_input = prompt_value !== false ? '<br/><br/><input type="text" id="response" value="' + prompt_value + '" style="width:85%" autofocus />' : '';

                var modal = $('<div class="modal hide fade" role="dialog"> \
<div class="modal-header">  \
    <button type="button" class="close cancel" data-dismiss="modal" aria-hidden="true">×</button>  \
<h3 id="myModalLabel">' + title + '</h3>  \
</div>  \
<div class="modal-body">' + text + prompt_input + '</div>  \
<div class="modal-footer">  \
    <button class="btn btn-primary cancel" data-dismiss="modal">' + fm_strings["accept"] + '</button> \
    <button class="btn" data-dismiss="modal" aria-hidden="true">' + fm_strings["cancel"] + '</button> \
</div>  \
</div>');

                //Invoke cancel callback
                $(modal).find('.cancel').click(function() {
                    if (cancel)
                        cancel();
                });

                //Invoke ok callback
                $(modal).find('.btn-primary').click(function() {
                    ok($(modal).find('#response').val());
                });

                //Submit on Enter press
                $(modal).find('#response').keypress(function(event) {
                    if (event.which == 13) {
                        event.preventDefault();
                        $(modal).find('.btn-primary').click();
                    }
                });

                $(modal).modal('show').on('hidden', function() {
                    $(modal).remove();
                });
            } else {
                var resp = prompt_value ? prompt(text, prompt_value) : confirm(text);

                if (resp)
                    ok(resp);
                else if (cancel)
                    cancel();
            }
        }

        var upload_files = function(files) {
            for (var i = 0; i < files.length; i++) {

                (function(file) {
                    //Show upload progress
                    var row = add_upload_queue(file);
                    var progress_bar = $(row).find('.progress');

                    //Post XHR request
                    var data = new FormData();
                    data.append('action', 'upload');
                    data.append('folder', current_folder);
                    data.append('files[]', file);

                    var xhr = $.ajax({
                        type: "POST",
                        url: fm_ajax_endpoint,
                        data: data,
                        dataType: 'json',
                        processData: false,
                        cache: false,
                        contentType: false,
                        success: function(data) {
                            $(progress_bar).addClass('progress-success');
                            if (data.file_html) {
                                add_file(data.file_html, 'show');
                            }
                        },
                        error: function() {
                            $(progress_bar).addClass('progress-danger').find('.bar').text('Error');
                        },
                        complete: function() {
                            $(progress_bar).removeClass('active').find('.bar').width('100%');

                            $(row).find('.cancel').hide();
                        },
                        xhr: function() {
                            myXhr = $.ajaxSettings.xhr();
                            if (myXhr.upload) {
                                myXhr.upload.addEventListener('progress', function(event) {
                                    if (event.lengthComputable) {
                                        var complete = (event.loaded / event.total * 100 | 0);
                                        $(progress_bar).width(complete + '%');
                                    }
                                }, false);
                            }
                            return myXhr;
                        },
                    });

                    //Cancel
                    $(row).find('.cancel').click(function() {
                        xhr.abort();
                        $(row).hide('slow');
                        return false;
                    });
                })(files[i]);
            }
        }

        var add_upload_queue = function(file) {
            var row = $('#sample-upload-row').clone();

            $(row).data('file', file);

            //Preview
            if (typeof FileReader != 'undefined' && file.type.match('image.*')) {
                var reader = new FileReader();
                reader.onload = function(event) {
                    var image = new Image();
                    image.src = event.target.result;

                    $(row).find('.preview').append(image);
                };

                reader.readAsDataURL(file);
            } else {
                var ext = file.name.substr(file.name.lastIndexOf('.') + 1);
                $(row).find('.preview').append(fm_file_icon.replace('%ext%', ext));
            }

            //Info
            $(row).find('.info').html(file.name + '<br/><span class="muted">' + (file.size ? (file.size / 1024 | 0) + ' KB' : '') + '</span>');

            //Append
            $(row).show().attr('id', '').appendTo($(manager).find('.upload-queue'));

            return row;
        }
    }



    //Initialize
    $('.jquery_fm').each(function() {
        initialize(this);
    })
});