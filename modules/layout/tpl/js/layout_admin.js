
/* After completion of the layout when you create a new function to request */
function completeInsertLayout(ret_obj) {
      var layout_srl = ret_obj['layout_srl'];
      var url = current_url.setQuery('act','dispLayoutAdminModify').setQuery('layout_srl',layout_srl);
      location.href = url;
}

/* Delete a layout */
function doDeleteLayout(layout_srl) {
    var fo_obj = jQuery('#fo_layout').get(0);
    fo_obj.layout_srl.value = layout_srl;
    procFilter(fo_obj, delete_layout);
}

function checkFile(f){
    var filename = jQuery('[name=user_layout_image]',f).val();
    if(/\.(gif|jpg|jpeg|gif|png|swf|flv)$/i.test(filename)){
        return true;
    }else{
        alert('only image and flash file');
        return false;
    }
}

function deleteFile(layout_srl,filename){
    var params ={
            "layout_srl":layout_srl
            ,"filename":filename
            };

    jQuery.exec_json('layout.procLayoutAdminUserImageDelete', params, function(data){
        document.location.reload();
    });
}

function addLayoutCopyInputbox()
{
	var html = '<tr>';
	html += '<td><input type="text" name="title[]" size="50" /></td>';
    html += '<td><input class="btn" type="button" value="'+addLang+'" onclick="addLayoutCopyInputbox()" /></td>';
	html += '</tr>';

	var it  = jQuery('#inputTable');
	it.append(html);
	
	it.find('SPAN.btn').hide();
	it.find('TR:last-child SPAN.btn').show();
}

(function($){

/* preview layout */
function doPreviewLayoutCode(layout_srl) {
	var fo  = $('#fo_layout');
	var act = fo.find('input[name=act]:first').val();
	fo.attr('target', '_LayoutPreview').find('input[name=act]').val('dispLayoutAdminPreview');
	fo.submit();
	//.removeAttr('target').find('input[name=act]').val(act);
}
window.doPreviewLayoutCode = doPreviewLayoutCode;

/* restore layout code */
function doResetLayoutCode(layout_srl) {
    procFilter($('#fo_layout')[0], reset_layout_code);
}
window.doResetLayoutCode = doResetLayoutCode;

var validator = xe.getApp('validator')[0];
validator.cast('ADD_CALLBACK', ['update_layout_code', function(form) {
	if (form.act.value != 'procLayoutAdminCodeUpdate') return true;

	var params={},data=$(form).serializeArray();
	$.each(data, function(i,field){ params[field.name] = field.value });

	exec_xml('layout', 'procLayoutAdminCodeUpdate', params, filterAlertMessage, ['error','message'], params, form);
	return false;
}]);

})(jQuery);
