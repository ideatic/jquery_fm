$(function() {
    //Initialize buttons functionality
    initialize($('.jquery_fm'));
});

function initialize(selector) {
    var selector = $(selector);

    var execute_action = function(file, data, success) {
        return $.post(fm_ajax_endpoint, data, function(data) {
            if (success)
                success(data);
        }).fail(function() {
            var message = fm_strings["ajax_error"].replace('%operation%', action).replace('%file%', $(file).find('h4').text());
            $(file).closest('.jquery_fm').prepend('<p><span class="label label-important">' + message + '</span></p>');
        });
    };

    //Delete button
    selector.find('.btn.delete').click(function() {
        var file = $(this).closest('.file');

        show_modal(fm_strings["delete"], fm_strings["confirm_delete"], false, function() {
            execute_action(file, {
                action: 'delete',
                file: $(file).data('file')
            }, function() {
                $(file).hide('slow');
            });
        });

        return false;
    });

    //Rename button
    selector.find('.btn.rename').click(function() {
        var file = $(this).closest('.file');

        show_modal(fm_strings["rename"], fm_strings["prompt_newname"], $(file).data('file'), function(new_name) {
            execute_action(file, {
                action: 'rename',
                src: $(file).data('file'),
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


    if (window.FormData) {
        //Enable drag-drop (if supported)
        if ('draggable' in document.createElement('span')) {
            // Makes sure the dataTransfer information is sent when we
            // Drop the item in the drop box.
            jQuery.event.props.push('dataTransfer');

            selector.find('.drag-drop-panel').bind('drop', function(e) {
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
        selector.find('input[type=file]').bind('change', function(e) {
            upload_files(this.files);
        });
    } else {
        //Show old-style submit button
        selector.find('.submit').show();
    }
}

function show_modal(title, text, prompt_value, ok, cancel) {
    if (jQuery().modal) {
        var prompt_input = prompt_value ? '<br/><br/><input type="text" id="response" value="' + prompt_value + '" autofocus />' : '';

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

        $(modal).find('.cancel').click(function() {
            if (cancel)
                cancel();
        });
        $(modal).find('.btn-primary').click(function() {
            ok($(modal).find('#response').val());
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

function upload_files(files) {
    for (var i = 0; i < files.length; i++) {

        (function(file) {
            //Show upload progress
            var row = add_upload_queue(file);
            var progress_bar = $(row).find('.progress');

            //Post XHR request
            var data = new FormData();
            data.append('action', 'upload');
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
                    if (data.file_html)
                    {
                        var new_file = $(data.file_html.trim()).hide();

                        if ($('.drag-drop-panel .file').length > 0) {
                            $(new_file).insertAfter($('.drag-drop-panel .file').last());
                        } else {
                            $('.drag-drop-panel').prepend(new_file);
                        }

                        $(new_file).show('slow');
                        initialize(new_file);
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

function add_upload_queue(file) {
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
    $(row).show().attr('id', '');
    $('.upload-queue').append(row);

    return row;
}